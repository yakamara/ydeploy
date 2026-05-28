<?php

namespace Alexplusde\Deploy\Command;

use rex;
use rex_addon;
use rex_search_it;
use rex_yrewrite;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Warms up the post-deployment cache by crawling URLs (typically from
 * sitemap.xml of each yrewrite domain) via multi-cURL, and rebuilds the
 * search_it index unless `--skip-search-it` is used.
 *
 * @internal
 */
final class Warmup extends AbstractCommand
{
    /** @var int default per-request timeout in seconds */
    private const DEFAULT_TIMEOUT = 30;

    /** @var int default number of parallel requests */
    private const DEFAULT_CONCURRENCY = 5;

    protected function configure(): void
    {
        $this
            ->setName('ydeploy:warmup')
            ->setDescription('Warms up the cache after a deployment (crawls sitemap.xml and rebuilds search indexes)')
            ->addOption('url', 'u', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'URL(s) to warm up. If omitted, sitemap.xml of each yrewrite domain (or the configured server URL) is used.')
            ->addOption('no-sitemap', null, InputOption::VALUE_NONE, 'Do not auto-discover URLs from sitemap.xml; only request the domain root(s).')
            ->addOption('skip-search-it', null, InputOption::VALUE_NONE, 'Skip rebuilding the search_it index.')
            ->addOption('concurrency', null, InputOption::VALUE_REQUIRED, 'Number of parallel HTTP requests', (string) self::DEFAULT_CONCURRENCY)
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Per-request timeout in seconds', (string) self::DEFAULT_TIMEOUT)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $io->title('YDeploy warmup');

        $canWarmupUrls = function_exists('curl_init') && function_exists('curl_multi_init');
        if (!$canWarmupUrls) {
            $io->warning('cURL extension is not available. Skipping URL warmup.');
        }

        $concurrency = max(1, (int) $input->getOption('concurrency'));
        $timeout = max(1, (int) $input->getOption('timeout'));
        $useSitemap = !$input->getOption('no-sitemap');

        /** @var list<string> $urlOption */
        $urlOption = $input->getOption('url');

        $urls = [];

        if ($canWarmupUrls) {
            if ($urlOption) {
                foreach ($urlOption as $url) {
                    $urls[] = $url;
                }
            } else {
                $baseUrls = $this->getBaseUrls();

                if (!$baseUrls) {
                    $io->warning('No base URL could be determined for warmup. Use --url to specify URLs explicitly.');
                } elseif ($useSitemap) {
                    foreach ($baseUrls as $baseUrl) {
                        $sitemapUrl = rtrim($baseUrl, '/') . '/sitemap.xml';
                        $io->text(sprintf('Discovering URLs from <info>%s</info>', $sitemapUrl));

                        $discovered = $this->fetchSitemapUrls($sitemapUrl, $timeout);
                        if (!$discovered) {
                            $io->text(sprintf('  No URLs found, falling back to <info>%s</info>', $baseUrl));
                            $urls[] = $baseUrl;
                            continue;
                        }
                        $io->text(sprintf('  Found <info>%d</info> URL(s)', count($discovered)));
                        foreach ($discovered as $u) {
                            $urls[] = $u;
                        }
                    }
                } else {
                    foreach ($baseUrls as $baseUrl) {
                        $urls[] = $baseUrl;
                    }
                }
            }
            $urls = array_values(array_unique($urls));

            if (!$urls) {
                $io->warning('No URLs to warm up.');
            } else {
                $io->section(sprintf('Warming up %d URL(s) (concurrency: %d)', count($urls), $concurrency));
                [$success, $failed] = $this->warmupUrls($urls, $concurrency, $timeout, $io);

                if ($failed) {
                    $io->warning(sprintf('%d URL(s) warmed up, %d failed.', $success, $failed));
                } else {
                    $io->success(sprintf('%d URL(s) warmed up successfully.', $success));
                }
            }
        }

        if (!$input->getOption('skip-search-it')) {
            $this->rebuildSearchIt($io);
        }

        return Command::SUCCESS;
    }

    /**
     * Returns the list of base URLs to warm up. Uses yrewrite domains when
     * the yrewrite addon is available, otherwise falls back to the
     * configured REDAXO server URL.
     *
     * @return list<string>
     */
    private function getBaseUrls(): array
    {
        $urls = [];

        if (rex_addon::get('yrewrite')->isAvailable() && class_exists(rex_yrewrite::class)) {
            foreach (rex_yrewrite::getDomains() as $domain) {
                $url = $domain->getUrl();
                if ($url && preg_match('#^https?://#i', $url)) {
                    $urls[] = rtrim($url, '/');
                }
            }
        }

        if (!$urls) {
            $server = rex::getServer();
            if ($server) {
                $urls[] = rtrim($server, '/');
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Fetches a sitemap.xml URL and extracts the contained URLs. Recursively
     * follows sitemap index files (up to one level of nesting).
     *
     * @return list<string>
     */
    private function fetchSitemapUrls(string $sitemapUrl, int $timeout, int $depth = 0): array
    {
        $content = $this->fetchUrl($sitemapUrl, $timeout);
        if (null === $content) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (false === $xml) {
            return [];
        }

        $urls = [];
        $name = $xml->getName();

        if ('sitemapindex' === $name && $depth < 1) {
            foreach ($xml->sitemap as $entry) {
                $loc = trim((string) $entry->loc);
                if ('' === $loc) {
                    continue;
                }
                foreach ($this->fetchSitemapUrls($loc, $timeout, $depth + 1) as $u) {
                    $urls[] = $u;
                }
            }
        } else {
            foreach ($xml->url as $entry) {
                $loc = trim((string) $entry->loc);
                if ('' !== $loc) {
                    $urls[] = $loc;
                }
            }
        }

        return $urls;
    }

    /**
     * Single blocking HTTP GET, used for sitemap discovery.
     */
    private function fetchUrl(string $url, int $timeout): ?string
    {
        $ch = curl_init($url);
        if (false === $ch) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'ydeploy-warmup',
            CURLOPT_FAILONERROR => true,
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        if (!is_string($result)) {
            return null;
        }

        return $result;
    }

    /**
     * Warms up the given URLs in parallel using curl_multi.
     *
     * @param list<string> $urls
     *
     * @return array{0: int, 1: int} [successCount, failureCount]
     */
    private function warmupUrls(array $urls, int $concurrency, int $timeout, SymfonyStyle $io): array
    {
        $success = 0;
        $failed = 0;

        $chunks = array_chunk($urls, $concurrency);

        foreach ($chunks as $chunk) {
            $mh = curl_multi_init();
            $handles = [];

            foreach ($chunk as $url) {
                $ch = curl_init($url);
                if (false === $ch) {
                    ++$failed;
                    continue;
                }
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_NOBODY => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_CONNECTTIMEOUT => $timeout,
                    CURLOPT_USERAGENT => 'ydeploy-warmup',
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[] = ['handle' => $ch, 'url' => $url];
            }

            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh, 1.0);
                }
            } while ($active && CURLM_OK === $status);

            foreach ($handles as $entry) {
                $ch = $entry['handle'];
                $url = $entry['url'];
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);

                if ('' === $error && $httpCode >= 200 && $httpCode < 400) {
                    ++$success;
                    if ($io->isVerbose()) {
                        $io->text(sprintf('  <info>%d</info> %s', $httpCode, $url));
                    }
                } else {
                    ++$failed;
                    $io->text(sprintf('  <comment>%s</comment> %s%s', 0 === $httpCode ? 'ERR' : (string) $httpCode, $url, '' !== $error ? ' (' . $error . ')' : ''));
                }

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            curl_multi_close($mh);
        }

        return [$success, $failed];
    }

    private function rebuildSearchIt(SymfonyStyle $io): void
    {
        $addon = rex_addon::get('search_it');
        if (!$addon->isAvailable()) {
            return;
        }

        if (!class_exists(rex_search_it::class)) {
            $io->note('search_it is available but rex_search_it class was not found; skipping index rebuild.');
            return;
        }

        $io->section('Rebuilding search_it index');

        try {
            $searchIt = new rex_search_it();
            $searchIt->deleteCache();
            $result = $searchIt->generateIndex();

            $indexed = is_array($result) && isset($result['count_indexed_articles']) ? (int) $result['count_indexed_articles'] : null;

            if (null !== $indexed) {
                $io->success(sprintf('search_it: %d article(s) indexed.', $indexed));
            } else {
                $io->success('search_it index rebuilt.');
            }
        } catch (Throwable $e) {
            $io->warning('search_it index rebuild failed: ' . $e->getMessage());
        }
    }
}

\class_alias(Warmup::class, 'rex_ydeploy_command_warmup');
