<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\CreateTechPartsService;

class CreateTechPartsCommand extends ShopwareCommand
{
    private $createTechPartsService;

    public function __construct(CreateTechPartsService $createTechPartsService)
    {
        parent::__construct();
        $this->createTechPartsService = $createTechPartsService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:create_tech_parts')
            ->setDescription('Create Tech Parts (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->createTechPartsService->execute();
        $output->writeln("\nSuccess");
    }
}
