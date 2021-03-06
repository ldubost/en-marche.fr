<?php

namespace AppBundle\Membership;

use AppBundle\Entity\Adherent;
use AppBundle\Entity\AdherentActivationToken;
use AppBundle\Mailjet\MailjetService;
use AppBundle\Mailjet\Message\AdherentAccountActivationMessage;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MembershipRequestHandler
{
    private $adherentFactory;
    private $urlGenerator;
    private $mailjet;
    private $manager;

    public function __construct(
        AdherentFactory $adherentFactory,
        UrlGeneratorInterface $urlGenerator,
        MailjetService $mailjet,
        ObjectManager $manager
    ) {
        $this->adherentFactory = $adherentFactory;
        $this->urlGenerator = $urlGenerator;
        $this->mailjet = $mailjet;
        $this->manager = $manager;
    }

    public function handle(MembershipRequest $membershipRequest)
    {
        $adherent = $this->adherentFactory->createFromMembershipRequest($membershipRequest);
        $token = AdherentActivationToken::generate($adherent);

        $this->manager->persist($adherent);
        $this->manager->persist($token);
        $this->manager->flush();

        $activationUrl = $this->generateMembershipActivationUrl($adherent, $token);
        $this->mailjet->sendMessage(AdherentAccountActivationMessage::createFromAdherent($adherent, $activationUrl));

        $membershipRequest->setAdherent($adherent);
    }

    private function generateMembershipActivationUrl(Adherent $adherent, AdherentActivationToken $token)
    {
        $params = [
            'adherent_uuid' => (string) $adherent->getUuid(),
            'activation_token' => (string) $token->getValue(),
        ];

        return $this->urlGenerator->generate('app_membership_activate', $params, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
