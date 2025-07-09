<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\UpdateProductsQuantityPriceService;

class UpdateProductsQuantityPriceCommand extends ShopwareCommand
{
    private $updateProductsQuantityPriceService;

    public function __construct(UpdateProductsQuantityPriceService $updateProductsQuantityPriceService)
    {
        parent::__construct();
        $this->updateProductsQuantityPriceService = $updateProductsQuantityPriceService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:update_products_quantity_price')
            ->setDescription('Update Products Quantity And Price (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->updateProductsQuantityPriceService->execute();
        $output->writeln("\nSuccess");
    }
}
