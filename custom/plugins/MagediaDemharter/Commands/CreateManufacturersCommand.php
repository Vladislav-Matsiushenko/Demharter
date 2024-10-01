<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\CreateManufacturersService;

class CreateManufacturersCommand extends ShopwareCommand
{
    private $createManufacturersService;

    public function __construct(CreateManufacturersService $createManufacturersService)
    {
        parent::__construct();
        $this->createManufacturersService = $createManufacturersService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:create_manufacturers')
            ->setDescription('Create Manufacturers (Demharter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->createManufacturersService->execute();
        $output->writeln("\nSuccess");
    }
}
