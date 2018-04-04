<?php

namespace Pim\Bundle\CustomEntityBundle\Tests\Jobs\Import;

use Acme\Bundle\CustomBundle\Entity\Brand;
use Acme\Bundle\CustomBundle\Entity\Color;
use Acme\Bundle\CustomBundle\Entity\Fabric;
use Acme\Bundle\CustomBundle\Entity\Pictogram;
use Akeneo\Bundle\BatchBundle\Command\BatchCommand;
use Pim\Bundle\CustomEntityBundle\Tests\Jobs\AbstractJobTestCase;

/**
 * @author    Mathias METAYER <mathias.metayer@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CustomEntityImportTest extends AbstractJobTestCase
{
    public function testImportSimpleCustomEntity()
    {
        $this->createReferenceData(Color::class, [
            'code' => 'blue',
            'name' => 'Some name',
            'hex' => '#1207FE',
            'red' => 18,
            'green' => 7,
            'blue' => 254,
        ]);

        $colors = $this->manager->findAll(Color::class);
        $this->assertCount(1, $colors);
        $blue = $colors[0];
        $this->assertEquals('Some name', $blue->getName());
        $this->assertEquals(18, $blue->getRed());

        $this->createJobInstance(
            'csv_colors_import',
            'import',
            'color',
            static::DATA_FILE_PATH . 'colors.csv'
        );
        $batchStatus = $this->launch('csv_colors_import');

        $this->assertEquals(BatchCommand::EXIT_SUCCESS_CODE, $batchStatus);

        $colors = $this->manager->findAll(Color::class);
        $this->assertCount(3, $colors);

        $this->assertEquals('Blue', $blue->getName());
        $this->assertEquals('#0000FF', $blue->getHex());
        $this->assertEquals(0, $blue->getRed());
        $this->assertEquals(0, $blue->getGreen());
        $this->assertEquals(255, $blue->getBlue());
    }

    public function testImportLinkedCustomEntity()
    {
        $this->createReferenceData(Brand::class, ['code' => 'super_brand']);

        $brands = $this->manager->findAll(Brand::class);
        $this->assertCount(1, $brands);
        $superBrand = $brands[0];
        $this->assertEquals('super_brand', $superBrand->getCode());
        $this->assertNull($superBrand->getFabric());

        $this->createJobInstance(
            'csv_fabrics_import',
            'import',
            'fabric',
            static::DATA_FILE_PATH . 'fabrics.csv'
        );
        $batchStatus = $this->launch('csv_fabrics_import');
        $this->assertEquals(BatchCommand::EXIT_SUCCESS_CODE, $batchStatus);

        $this->createJobInstance(
            'csv_brands_import',
            'import',
            'brand',
            static::DATA_FILE_PATH . 'brands.csv'
        );
        $batchStatus = $this->launch('csv_brands_import');
        $this->assertEquals(BatchCommand::EXIT_SUCCESS_CODE, $batchStatus);

        $brands = $this->manager->findAll(Brand::class);

        $this->assertCount(3, $brands);
        $superBrand = $brands[0];
        $this->assertInstanceOf(Fabric::class, $superBrand->getFabric());
        $this->assertEquals('Another fabric', $superBrand->getFabric()->getName());
    }

    public function testImportTranslatableReferenceData()
    {
        $this->activateLocales('fr_FR', 'de_DE');

        $this->createReferenceData(
            Pictogram::class,
            [
                'code'   => 'picto_1',
                'labels' => [
                    'en_US' => 'an English label',
                ],
            ]
        );

        $pictos = $this->manager->findAll(Pictogram::class);
        $this->assertCount(1, $pictos);
        $picto1 = $pictos[0];
        $this->assertCount(1, $picto1->getTranslations());
        $this->assertEquals('an English label', $picto1->getTranslation('en_US')->getLabel());

        $this->createJobInstance(
            'csv_pictos_import',
            'import',
            'pictogram',
            static::DATA_FILE_PATH . 'pictos.csv'
        );
        $batchStatus = $this->launch('csv_pictos_import');
        $this->assertEquals(BatchCommand::EXIT_SUCCESS_CODE, $batchStatus);

        $pictos = $this->manager->findAll(Pictogram::class);

        $this->assertCount(2, $pictos);
        $picto1 = $pictos[0];
        $this->assertCount(3, $picto1->getTranslations());
        $this->assertEquals('label 1 en', $picto1->getTranslation('en_US')->getLabel());
        $this->assertEquals('label 1 fr', $picto1->getTranslation('fr_FR')->getLabel());
        $this->assertEquals('label 1 de', $picto1->getTranslation('de_DE')->getLabel());
    }
}
