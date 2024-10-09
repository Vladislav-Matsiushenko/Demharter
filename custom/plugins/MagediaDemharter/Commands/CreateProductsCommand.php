<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\CreateProductsService;

class CreateProductsCommand extends ShopwareCommand
{
    private $createProductsService;

    public function __construct(CreateProductsService $createProductsService)
    {
        parent::__construct();
        $this->createProductsService = $createProductsService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:create_products')
            ->setDescription('Create Products (Demharter)')
            ->addArgument(
                'number',
                InputArgument::OPTIONAL,
                'Products Number'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->createProductsService->execute($input->getArgument('number'));
        $output->writeln("\nSuccess");
    }
}
