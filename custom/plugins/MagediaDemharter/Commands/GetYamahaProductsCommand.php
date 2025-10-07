<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\GetYamahaProductsService;

class GetYamahaProductsCommand extends ShopwareCommand
{
    private $getYamahaProductsService;

    public function __construct(GetYamahaProductsService $getYamahaProductsService)
    {
        parent::__construct();
        $this->getYamahaProductsService = $getYamahaProductsService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:get_yamaha_products')
            ->setDescription('Get Yamaha Products (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getYamahaProductsService->execute();
        $output->writeln("\nSuccess");
    }
}
