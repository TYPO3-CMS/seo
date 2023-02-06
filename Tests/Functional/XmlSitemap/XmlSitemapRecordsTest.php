<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Seo\Tests\Functional\XmlSitemap;

use TYPO3\CMS\Frontend\Tests\Functional\SiteHandling\AbstractTestCase;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Contains functional tests for the XmlSitemap Index
 */
class XmlSitemapRecordsTest extends AbstractTestCase
{
    protected $coreExtensionsToLoad = ['seo'];

    /**
     * @var array<string, mixed>
     */
    protected $configurationToUseInTestInstance = [
        'FE' => [
            'cacheHash' => [
                'enforceValidation' => false,
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages-sitemap.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sys_category.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sys_news.csv');
        $this->setUpFrontendRootPage(
            1,
            [
                'constants' => ['EXT:seo/Configuration/TypoScript/XmlSitemap/constants.typoscript'],
                'setup' => [
                    'EXT:seo/Configuration/TypoScript/XmlSitemap/setup.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/records.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/content.typoscript',
                ],
            ]
        );

        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1, 'http://localhost/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/'),
                $this->buildLanguageConfiguration('FR', '/fr'),
            ]
        );
    }

    /**
     * @test
     * @dataProvider sitemapEntriesToCheck
     */
    public function checkIfSiteMapIndexContainsSysCategoryLinks(string $sitemap, string $host, array $expectedEntries, array $notExpectedEntries): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest($host))->withQueryParameters(
                [
                    'type' => 1533906435,
                    'sitemap' => $sitemap,
                ]
            )
        );

        self::assertEquals(200, $response->getStatusCode());
        self::assertArrayHasKey('Content-Type', $response->getHeaders());
        self::assertEquals('application/xml;charset=utf-8', $response->getHeader('Content-Type')[0]);
        self::assertArrayHasKey('X-Robots-Tag', $response->getHeaders());
        self::assertEquals('noindex', $response->getHeader('X-Robots-Tag')[0]);
        self::assertArrayHasKey('Content-Length', $response->getHeaders());
        $stream = $response->getBody();
        $stream->rewind();
        $content = $stream->getContents();

        foreach ($expectedEntries as $expectedEntry) {
            self::assertStringContainsString($expectedEntry, $content);
        }

        foreach ($notExpectedEntries as $notExpectedEntry) {
            self::assertStringNotContainsString($notExpectedEntry, $content);
        }

        self::assertGreaterThan(0, $response->getHeader('Content-Length')[0]);
    }

    /**
     * @return array[]
     */
    public function sitemapEntriesToCheck(): array
    {
        return [
            'default-language' => [
                'records',
                'http://localhost/',
                [
                    'http://localhost/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=2&amp;',
                    '<priority>0.5</priority>',
                ],
                [
                    'http://localhost/?tx_example_category%5Bid%5D=3&amp;',
                    'http://localhost/fr/?tx_example_category%5Bid%5D=3&amp;',
                ],
            ],
            'french-language' => [
                'records',
                'http://localhost/fr',
                [
                    'http://localhost/fr/?tx_example_category%5Bid%5D=3&amp;',
                    '<priority>0.5</priority>',
                ],
                [
                    'http://localhost/fr/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/fr/?tx_example_category%5Bid%5D=2&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=2&amp;',
                ],
            ],
            'only-records-in-live-workspace-should-be-shown-when-not-in-workspace-mode' => [
                'records',
                'http://localhost/',
                [
                    'http://localhost/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=2&amp;',
                ],
                [
                    'http://localhost/?tx_example_category%5Bid%5D=4&amp;',
                ],
            ],
            'non-workspace-tables-should-work-fine' => [
                'records_without_workspace_settings',
                'http://localhost/',
                [
                    'http://localhost/?tx_example_news%5Bid%5D=1&amp;',
                    'http://localhost/?tx_example_news%5Bid%5D=2&amp;',
                ],
                [],
            ],
        ];
    }

    /**
     * @test
     */
    public function checkIfSiteMapIndexContainsCustomChangeFreqAndPriorityValues(): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))->withQueryParameters(
                [
                    'id' => 1,
                    'type' => 1533906435,
                    'sitemap' => 'content',
                ]
            )
        );

        self::assertEquals(200, $response->getStatusCode());
        self::assertArrayHasKey('Content-Length', $response->getHeaders());
        $stream = $response->getBody();
        $stream->rewind();
        $content = $stream->getContents();

        self::assertStringContainsString('<changefreq>hourly</changefreq>', $content);
        self::assertStringContainsString('<priority>0.7</priority>', $content);

        self::assertGreaterThan(0, $response->getHeader('Content-Length')[0]);
    }

    /**
     * @test
     * @dataProvider additionalWhereTypoScriptConfigurationsToCheck
     */
    public function checkSiteMapWithDifferentTypoScriptConfigs(string $sitemap, array $expectedEntries, array $notExpectedEntries): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))->withQueryParameters(
                [
                    'id' => 1,
                    'type' => 1533906435,
                    'sitemap' => $sitemap,
                ]
            )
        );

        self::assertEquals(200, $response->getStatusCode());
        self::assertArrayHasKey('Content-Length', $response->getHeaders());
        $stream = $response->getBody();
        $stream->rewind();
        $content = $stream->getContents();

        foreach ($expectedEntries as $expectedEntry) {
            self::assertStringContainsString($expectedEntry, $content);
        }

        foreach ($notExpectedEntries as $notExpectedEntry) {
            self::assertStringNotContainsString($notExpectedEntry, $content);
        }

        self::assertGreaterThan(0, $response->getHeader('Content-Length')[0]);
    }

    /**
     * @return array[]
     */
    public function additionalWhereTypoScriptConfigurationsToCheck(): array
    {
        return [
            [
                'records_with_additional_where',
                [
                    'http://localhost/?tx_example_category%5Bid%5D=1&amp;',
                ],
                [
                    'http://localhost/?tx_example_category%5Bid%5D=2&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=3&amp;',
                ],
            ],
            [
                'records_with_additional_where_starting_with_logical_operator',
                [
                    'http://localhost/?tx_example_category%5Bid%5D=2&amp;',
                ],
                [
                    'http://localhost/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=3&amp;',
                ],
            ],
        ];
    }
}
