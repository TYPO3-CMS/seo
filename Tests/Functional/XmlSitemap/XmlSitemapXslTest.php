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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Tests\Functional\SiteHandling\AbstractTestCase;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class XmlSitemapXslTest extends AbstractTestCase
{
    protected array $coreExtensionsToLoad = ['seo'];

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'encryptionKey' => '4408d27a916d51e624b69af3554f516dbab61037a9f7b9fd6f81b4d3bedeccb6',
        ],
        'FE' => [
            'cacheHash' => [
                'requireCacheHashPresenceParameters' => ['value', 'testing[value]', 'tx_testing_link[value]'],
                'excludedParameters' => ['tx_testing_link[excludedValue]'],
                'enforceValidation' => false,
            ],
            'debug' => false,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages-sitemap.csv');
    }

    #[DataProvider('getXslFilePathsDataProvider')]
    #[Test]
    public function checkIfDefaultSitemapReturnsDefaultXsl(array $typoscriptSetupFiles, string $sitemap, string $xslFilePath): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'constants' => ['EXT:seo/Configuration/TypoScript/XmlSitemap/constants.typoscript'],
                'setup' => $typoscriptSetupFiles,
            ]
        );

        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1, 'http://localhost/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/'),
            ]
        );

        $config = [
            'id' => 1,
            'type' => 1533906435,
        ];

        if (!empty($sitemap)) {
            $config['tx_seo[sitemap]'] = $sitemap;
        }

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))->withQueryParameters($config)
        );

        self::assertMatchesRegularExpression('/<\?xml-stylesheet type="text\/xsl" href="' . $xslFilePath . '"\?>/', (string)$response->getBody());
    }

    public static function getXslFilePathsDataProvider(): array
    {
        return [
            [
                [
                    'EXT:seo/Configuration/TypoScript/XmlSitemap/setup.typoscript',
                ],
                '',
                '\/typo3\/sysext\/seo\/Resources\/Public\/CSS\/Sitemap.xsl',
            ],
            [
                [
                    'EXT:seo/Configuration/TypoScript/XmlSitemap/setup.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/sitemap-xsl1.typoscript',
                ],
                '',
                '\/typo3\/sysext\/seo\/Tests\/Functional\/Fixtures\/XslFile1.xsl',
            ],
            [
                [
                    'EXT:seo/Configuration/TypoScript/XmlSitemap/setup.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/sitemap-xsl2.typoscript',
                ],
                '',
                '\/typo3\/sysext\/seo\/Tests\/Functional\/Fixtures\/XslFile2.xsl',
            ],
            [
                [
                    'EXT:seo/Configuration/TypoScript/XmlSitemap/setup.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/records.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/sitemap-xsl3.typoscript',
                ],
                '',
                '\/typo3\/sysext\/seo\/Tests\/Functional\/Fixtures\/XslFile1.xsl',
            ],
            [
                [
                    'EXT:seo/Configuration/TypoScript/XmlSitemap/setup.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/records.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/sitemap-xsl3.typoscript',
                ],
                'records',
                '\/typo3\/sysext\/seo\/Tests\/Functional\/Fixtures\/XslFile3.xsl',
            ],
            [
                [
                    'EXT:seo/Configuration/TypoScript/XmlSitemap/setup.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/records.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/sitemap-xsl3.typoscript',
                ],
                'pages',
                '\/typo3\/sysext\/seo\/Tests\/Functional\/Fixtures\/XslFile1.xsl',
            ],
        ];
    }
}
