<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\UpdateProductsPriceService;

class UpdateProductsPriceCommand extends ShopwareCommand
{
    private $updateProductsPriceService;

    public function __construct(UpdateProductsPriceService $updateProductsPriceService)
    {
        parent::__construct();
        $this->updateProductsPriceService = $updateProductsPriceService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:update_products_price')
            ->setDescription('Update Products Price (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->updateProductsPriceService->execute();
        $output->writeln("\nSuccess");
    }
}
