<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\DeleteTechPartsProductsCategoriesService;

class DeleteTechPartsProductsCategoriesCommand extends ShopwareCommand
{
    private $deleteTechPartsProductsCategoriesService;

    public function __construct(DeleteTechPartsProductsCategoriesService $deleteTechPartsProductsCategoriesService)
    {
        parent::__construct();
        $this->deleteTechPartsProductsCategoriesService = $deleteTechPartsProductsCategoriesService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:delete_tech_parts_products_categories')
            ->setDescription('Delete Tech Parts, Products And Categories (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->deleteTechPartsProductsCategoriesService->execute();
        $output->writeln("\nSuccess");
    }
}
