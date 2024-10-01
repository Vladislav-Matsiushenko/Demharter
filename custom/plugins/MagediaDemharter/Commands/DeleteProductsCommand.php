<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\DeleteProductsService;

class DeleteProductsCommand extends ShopwareCommand
{
    private $deleteProductsService;

    public function __construct(DeleteProductsService $deleteProductsService)
    {
        parent::__construct();
        $this->deleteProductsService = $deleteProductsService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:delete_products')
            ->setDescription('Delete Products (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->deleteProductsService->execute();
        $output->writeln("\nSuccess");
    }
}
