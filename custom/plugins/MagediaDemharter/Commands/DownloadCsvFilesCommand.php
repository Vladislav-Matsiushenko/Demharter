<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\DownloadCsvFilesService;

class DownloadCsvFilesCommand extends ShopwareCommand
{
    private $downloadCsvFilesService;

    public function __construct(DownloadCsvFilesService $downloadCsvFilesService)
    {
        parent::__construct();
        $this->downloadCsvFilesService = $downloadCsvFilesService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:download_csv_files')
            ->setDescription('Download CSV Files (Demharter)')
            ->addArgument(
                'number',
                InputArgument::OPTIONAL,
                'Files Number'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->downloadCsvFilesService->execute($input->getArgument('number'));
        $output->writeln("\nSuccess");
    }
}
