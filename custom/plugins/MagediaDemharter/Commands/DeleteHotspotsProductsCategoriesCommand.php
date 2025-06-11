<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\DeleteHotspotsProductsCategoriesService;

class DeleteHotspotsProductsCategoriesCommand extends ShopwareCommand
{
    private $deleteHotspotsProductsCategoriesService;

    public function __construct(DeleteHotspotsProductsCategoriesService $deleteHotspotsProductsCategoriesService)
    {
        parent::__construct();
        $this->deleteHotspotsProductsCategoriesService = $deleteHotspotsProductsCategoriesService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:delete_hotspots_products_categories')
            ->setDescription('Delete Hotspots, Products And Categories (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->deleteHotspotsProductsCategoriesService->execute();
        $output->writeln("\nSuccess");
    }
}
