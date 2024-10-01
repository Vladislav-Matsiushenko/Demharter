<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\ImportProducts;

class ImportCommand extends ShopwareCommand
{
    private $importProductsService;

    public function __construct(ImportProducts $importProductsService)
    {
        parent::__construct();
        $this->importProductsService = $importProductsService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:import')
            ->setDescription('Import Products (demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->importProductsService->import();
        $output->writeln("\n");
        $output->writeln('Hello World');
    }

}
