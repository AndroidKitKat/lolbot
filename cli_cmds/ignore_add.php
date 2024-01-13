<?php
namespace lolbot\cli_cmds;
/**
 * @psalm-suppress InvalidGlobal
 */
global $entityManager;

use Doctrine\ORM\EntityRepository;
use GetOpt\Command;
use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use lolbot\entities\Network;
use lolbot\entities\Ignore;

class ignore_add extends Command
{
    public function __construct()
    {
        parent::__construct('ignore:add', $this->handle(...));
        $this->addOption(Option::create('n', 'network', GetOpt::MULTIPLE_ARGUMENT));
        $this->addOperand(Operand::create('hostmask', Operand::REQUIRED)->setValidation(fn ($it) => $it != ''));
        $this->addOperand(Operand::create('reason', Operand::OPTIONAL));
    }

    public function handle(GetOpt $getOpt): void {
        global $entityManager;
        if(count($getOpt->getOption("network")) == 0)
            die("Must specify a network\n");

        $ignore = new Ignore();
        $ignore->setHostmask($getOpt->getOperand('hostmask'));
        if($getOpt->getOperand('reason') !== null)
            $ignore->setReason($getOpt->getOperand('reason'));

        /** @var EntityRepository<Network> $repo */
        $repo = $entityManager->getRepository(Network::class);
        foreach($getOpt->getOption("network") as $net) {
            $network = $repo->find($net);
            if ($network === null)
                die("couldn't find that network id ($net)\n");
            $ignore->addToNetwork($network);
        }

        $entityManager->persist($ignore);
        $entityManager->flush();

        showdb::showdb();
    }
}
