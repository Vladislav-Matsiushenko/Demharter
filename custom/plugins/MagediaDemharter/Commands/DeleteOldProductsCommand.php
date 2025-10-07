<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\DeleteOldProductsService;

class DeleteOldProductsCommand extends ShopwareCommand
{
    private $deleteOldProductsService;

    public function __construct(DeleteOldProductsService $deleteOldProductsService)
    {
        parent::__construct();
        $this->deleteOldProductsService = $deleteOldProductsService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:delete_old_products')
            ->setDescription('Delete Old Products (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->deleteOldProductsService->execute();
        $output->writeln("\nSuccess");
    }
}
