<?php

namespace App\Command;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:hash-passwords',
    description: 'Re-hash all plain-text passwords in utilisateurs table to BCrypt (run once after importing DB)',
)]
class HashPasswordsCommand extends Command
{
    public function __construct(
        private UtilisateurRepository $repo,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Re-hashing plain-text passwords → BCrypt');

        $users = $this->repo->findAll();
        $count = 0;

        foreach ($users as $user) {
            $pwd = $user->getMotDePasse();

            // Skip already-hashed passwords (BCrypt starts with $2y$ or $2a$)
            if ($pwd && str_starts_with($pwd, '$2')) {
                $io->writeln('<comment>SKIP</comment> ' . $user->getEmail() . ' (already hashed)');
                continue;
            }

            if (!$pwd) {
                $io->writeln('<comment>SKIP</comment> ' . $user->getEmail() . ' (no password)');
                continue;
            }

            // Hash the plain-text password
            $hashed = $this->hasher->hashPassword($user, $pwd);
            $user->setMotDePasse($hashed);
            $count++;

            $io->writeln('<info>HASHED</info> ' . $user->getEmail());
        }

        $this->em->flush();

        $io->success("Done! $count password(s) were hashed.");
        $io->note('You can now log in with the original plain-text passwords you had in the DB.');

        return Command::SUCCESS;
    }
}
