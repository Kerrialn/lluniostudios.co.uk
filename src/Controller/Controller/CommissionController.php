<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Form\Form\CommissionHomeForm;
use App\Form\Form\CommissionProjectForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bespoke Commissions (design merge, Step 9): the prototype's page, with a
 * "For Your Home" and a "For Your Project" (trade) enquiry form.
 * Step 9.1: the page and both forms, checked but not yet saved or emailed
 * (Steps 9.2 and 9.3).
 */
final class CommissionController extends AbstractController
{
    public const CLIENT_HOME = 'home';

    public const CLIENT_PROJECT = 'project';

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    /**
     * ?for=home or ?for=project opens with that form chosen.
     */
    #[Route(path: '/bespoke-commissions', name: 'commissions', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $homeForm = $this->formFactory->createNamed('commission_home', CommissionHomeForm::class);
        $projectForm = $this->formFactory->createNamed('commission_project', CommissionProjectForm::class);

        $forms = [
            self::CLIENT_HOME => $homeForm,
            self::CLIENT_PROJECT => $projectForm,
        ];
        $client = (string) $request->query->get('for', '');
        foreach ($forms as $key => $form) {
            $form->handleRequest($request);
            if (! $form->isSubmitted()) {
                continue;
            }

            if ($form->isValid()) {
                // Step 9.2 saves the enquiry here.
                return $this->redirectToRoute('commissions', [
                    'for' => $key,
                ]);
            }

            $client = $key;
        }

        $submitted = $homeForm->isSubmitted() || $projectForm->isSubmitted();

        return $this->render('commissions/index.html.twig', [
            'homeForm' => $homeForm,
            'projectForm' => $projectForm,
            'client' => \in_array($client, [self::CLIENT_HOME, self::CLIENT_PROJECT], true) ? $client : '',
            'openRevealed' => $submitted || $client !== '',
        ], new Response(status: $submitted ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
