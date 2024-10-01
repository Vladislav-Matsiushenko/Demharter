<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\DeleteProducts;

class DeleteProductsCommand extends ShopwareCommand
{
    private $deleteProductsService;

    public function __construct(DeleteProducts $deleteProductsService)
    {
        parent::__construct();
        $this->deleteProductsService = $deleteProductsService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:delete_products')
            ->setDescription('Delete Products (demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->deleteProductsService->deleteProductsFromCsv();
        $output->writeln("\n");
        $output->writeln('Success');
    }

}
