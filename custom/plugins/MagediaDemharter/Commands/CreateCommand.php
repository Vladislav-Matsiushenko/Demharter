<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\CreateProducts;

class CreateCommand extends ShopwareCommand
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
            ->setName('demharter:create')
            ->setDescription('Create new products from Demharter file"')
            ->addArgument(
                'category',
                InputArgument::OPTIONAL,
                'Category For Import'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $category = $input->getArgument('category');
        $this->createProductsService->create($category);
        $output->writeln("\n");
        $output->writeln('Success');
    }
}
