<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\CreateCategoriesService;

class CreateCategoriesCommand extends ShopwareCommand
{
    private $createCategoriesService;

    public function __construct(CreateCategoriesService $createCategoriesService)
    {
        parent::__construct();
        $this->createCategoriesService = $createCategoriesService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:create_categories')
            ->setDescription('Create Categories (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->createCategoriesService->execute();
        $output->writeln("\nSuccess");
    }
}
