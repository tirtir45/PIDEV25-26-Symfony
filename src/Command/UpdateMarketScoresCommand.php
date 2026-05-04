<?php
// src/Command/UpdateMarketScoresCommand.php

namespace App\Command;

use App\Repository\ProjetsRepository;
use App\Service\MarketstackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateMarketScoresCommand extends Command
{
    protected static $defaultName = 'app:update-market-scores';

    public function __construct(
        private ProjetsRepository $projetRepository,
        private MarketstackService $marketstack,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projets = $this->projetRepository->findAll();
        $output->writeln(sprintf('Analyse de %d projets...', count($projets)));

        foreach ($projets as $projet) {
            if (!$this->marketstack->getSymbolForSector($projet->getSecteur())) {
                continue;
            }

            $output->write(sprintf('  - %s... ', $projet->getTitre()));
            
            $analysis = $this->marketstack->analyzeSectorTrend($projet);
            $projet->setMarketScore($analysis['score']);
            $projet->setMarketTrend($analysis['trend']);
            $projet->setMarketData($analysis);
            $projet->setMarketAnalyzedAt(new \DateTime());
            
            $this->em->flush();
            $output->writeln(sprintf('Score: %d', $analysis['score']));
        }

        $output->writeln('Analyse terminée !');
        return Command::SUCCESS;
    }
}