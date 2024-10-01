<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\DeleteCategoriesAndProducts;

class DeleteCategoriesAndProductsCommand extends ShopwareCommand
{
    private $deleteCategoriesService;

    public function __construct(DeleteCategoriesAndProducts $deleteCategoriesService)
    {
        parent::__construct();
        $this->deleteCategoriesService = $deleteCategoriesService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:delete_categories_and_products')
            ->setDescription('Delete Categories And Products (demharter)')
            ->addArgument(
                'category',
                InputArgument::OPTIONAL,
                'Category For Delete'
            );
        }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $category = $input->getArgument('category');
        $this->deleteCategoriesService->deleteCategoriesAndProducts($category);
        $output->writeln("\n");
        $output->writeln('Success');
    }

}
