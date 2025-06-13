<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\CreateHotspotsService;

class CreateHotspotsCommand extends ShopwareCommand
{
    private $createTechPartsService;

    public function __construct(CreateHotspotsService $createHotspotsService)
    {
        parent::__construct();
        $this->createHotspotsService = $createHotspotsService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:create_hotspots')
            ->setDescription('Create Hotspots (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->createHotspotsService->execute();
        $output->writeln("\nSuccess");
    }
}
