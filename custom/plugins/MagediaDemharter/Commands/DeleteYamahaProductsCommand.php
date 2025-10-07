<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\DeleteYamahaProductsService;

class DeleteYamahaProductsCommand extends ShopwareCommand
{
    private $deleteYamahaProductsService;

    public function __construct(DeleteYamahaProductsService $deleteYamahaProductsService)
    {
        parent::__construct();
        $this->deleteYamahaProductsService = $deleteYamahaProductsService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:delete_yamaha_products')
            ->setDescription('Delete Yamaha Products (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->deleteYamahaProductsService->execute();
        $output->writeln("\nSuccess");
    }
}
