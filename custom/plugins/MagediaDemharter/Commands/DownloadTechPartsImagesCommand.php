<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\DownloadTechPartsImagesService;

class DownloadTechPartsImagesCommand extends ShopwareCommand
{
    private $downloadTechPartsImagesService;

    public function __construct(DownloadTechPartsImagesService $downloadTechPartsImagesService)
    {
        parent::__construct();
        $this->downloadTechPartsImagesService = $downloadTechPartsImagesService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:download_tech_parts_images')
            ->setDescription('Download Tech Parts Images (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->downloadTechPartsImagesService->execute();
        $output->writeln("\nSuccess");
    }
}
