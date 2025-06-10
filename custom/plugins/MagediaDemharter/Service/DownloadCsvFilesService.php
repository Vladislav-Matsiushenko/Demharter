<?php

namespace MagediaDemharter\Service;

class DownloadCsvFilesService
{
// Local
    private $productDataCsvFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/ProductData.csv';
    private $categoryDataCsvFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/CategoryData.csv';
    private $hotspotDataCsvFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/HotspotData.csv';

// Staging
//    private $productDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/ProductData.csv';
//    private $categoryDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/CategoryData.csv';
//    private $hotspotDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/HotspotData.csv';

// Live
//    private $productDataCsvFilePath = '/usr/home/mipzhm/public_html/files/demharter/ProductData.csv';
//    private $categoryDataCsvFilePath = '/usr/home/mipzhm/public_html/files/demharter/CategoryData.csv';
//    private $hotspotDataCsvFilePath = '/usr/home/mipzhm/public_html/files/demharter/HotspotData.csv';

    private $productDataCsvFileUrl = 'https://www.dataparts.eu/media/files_public/c351df7cf04b705dedc109004e39aac0_file/Teileexport-grp-3.csv';
    private $categoryDataCsvFileUrl = 'https://dataparts.eu/export/LK3YrGPFzsoSnCFMpfc3sO5e/category_custom_export.csv';
    private $hotspotDataCsvFileUrl = 'https://dataparts.eu/export/LK3YrGPFzsoSnCFMpfc3sO5e/hotspot_custom_export.csv';

    public function execute($filesNumber = null)
    {
        $startTime = microtime(true);

        if (file_exists($this->productDataCsvFilePath)) {
            unlink($this->productDataCsvFilePath);
        }
        $fileContent = file_get_contents($this->productDataCsvFileUrl);
        if ($fileContent !== false && file_put_contents($this->productDataCsvFilePath, $fileContent) !== false) {
            echo "ProductData file was successfully downloaded\n";
        } else {
            echo "Error while downloading ProductData file\n";
        }

        if ($filesNumber != 1) {
            if (file_exists($this->categoryDataCsvFilePath)) {
                unlink($this->categoryDataCsvFilePath);
            }
            $fileContent = file_get_contents($this->categoryDataCsvFileUrl);
            if ($fileContent !== false && file_put_contents($this->categoryDataCsvFilePath, $fileContent) !== false) {
                echo "CategoryData file was successfully downloaded\n";
            } else {
                echo "Error while downloading CategoryData file\n";
            }

            if (file_exists($this->hotspotDataCsvFilePath)) {
                unlink($this->hotspotDataCsvFilePath);
            }
            $fileContent = file_get_contents($this->hotspotDataCsvFileUrl);
            if ($fileContent !== false && file_put_contents($this->hotspotDataCsvFilePath, $fileContent) !== false) {
                echo "HotspotData file was successfully downloaded\n";
            } else {
                echo "Error while downloading HotspotData file\n";
            }
        }

        $executionTime = (microtime(true) - $startTime);
        echo 'Downloading CSV files completed in ' . $executionTime . " seconds\n";
    }
}
