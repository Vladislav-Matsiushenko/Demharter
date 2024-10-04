<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\UpdateProductsQuantityService;

class UpdateProductsQuantityCommand extends ShopwareCommand
{
    private $updateProductsQuantityService;

    public function __construct(UpdateProductsQuantityService $updateProductsQuantityService)
    {
        parent::__construct();
        $this->updateProductsQuantityService = $updateProductsQuantityService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:update_products_quantity')
            ->setDescription('Update Products Quantity (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->updateProductsQuantityService->execute();
        $output->writeln("\nSuccess");
    }
}
