<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\CreateProducts;

class UpdateQtyCommand extends ShopwareCommand
{
    private $createProductsService;

    public function __construct(CreateProducts $createProductsService)
    {
        parent::__construct();
        $this->createProductsService = $createProductsService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:update_qty')
            ->setDescription('Update products qty from Demharter file"');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->createProductsService->create(null, true);
        $output->writeln("\n");
        $output->writeln('Success');
    }
}
