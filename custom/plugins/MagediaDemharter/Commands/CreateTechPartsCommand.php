<?php

namespace MagediaDemharter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagediaDemharter\Service\CreateTechParts;

class CreateTechPartsCommand extends ShopwareCommand
{
    private $createTechPartsService;

    public function __construct(CreateTechParts $createTechPartsService)
    {
        parent::__construct();
        $this->createTechPartsService = $createTechPartsService;
    }

    protected function configure()
    {
        $this
            ->setName('demharter:create_tech_parts')
            ->setDescription('Create new tech parts from Demharter file"')
            ->addArgument(
                'category',
                InputArgument::OPTIONAL,
                'Category For Import'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $category = $input->getArgument('category');
        $this->createTechPartsService->create($category);
        $output->writeln("\n");
        $output->writeln('Success');
    }
}
