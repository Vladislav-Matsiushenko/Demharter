<?php

namespace MagediaDemharter\Service;

class DownloadCsvFilesService
{
// Local
    private $productsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/ProductsData.csv';
    private $techPartsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.csv';

// Staging
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/ProductsData.csv';
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.csv';

// Live
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/ProductsData.csv';
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/TechPartsData.csv';
    private $productsDataCsvFileUrl = 'https://www.dataparts.eu/media/files_public/c351df7cf04b705dedc109004e39aac0_file/Teileexport-grp-3.csv';
    private $techPartsDataCsvFileUrl = 'https://www.dataparts.eu/media/files_public/e1d6bb2bf5293105f019865e9904d969_file/cat_data.csv';

    public function execute($filesNumber = null)
    {
        $startTime = microtime(true);

        if (file_exists($this->productsDataCsvFilePath)) {
            unlink($this->productsDataCsvFilePath);
        }
        $fileContent = file_get_contents($this->productsDataCsvFileUrl);
        if ($fileContent !== false && file_put_contents($this->productsDataCsvFilePath, $fileContent) !== false) {
            echo "ProductsData file was successfully downloaded\n";
        } else {
            echo "Error while downloading ProductsData file\n";
        }

        if ($filesNumber != 1) {
            if (file_exists($this->techPartsDataCsvFilePath)) {
                unlink($this->techPartsDataCsvFilePath);
            }
            $fileContent = file_get_contents($this->techPartsDataCsvFileUrl);
            if ($fileContent !== false && file_put_contents($this->techPartsDataCsvFilePath, $fileContent) !== false) {
                echo "TechPartsData file was successfully downloaded\n";
            } else {
                echo "Error while downloading TechPartsData file\n";
            }
        }

        $executionTime = (microtime(true) - $startTime);
        echo 'Downloading CSV files completed in ' . $executionTime . " seconds\n";
    }
}
