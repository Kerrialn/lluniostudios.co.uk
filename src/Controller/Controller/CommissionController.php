<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Entity\CommissionEnquiry;
use App\Entity\CommissionEnquiryFile;
use App\Form\Form\CommissionFields;
use App\Form\Form\CommissionHomeForm;
use App\Form\Form\CommissionProjectForm;
use App\Service\CommissionFileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bespoke Commissions (design merge, Step 9): the prototype's page, with a
 * "For Your Home" and a "For Your Project" (trade) enquiry form. A complete
 * enquiry is saved with its files (kept privately) for the studio to follow
 * up in admin. Emails come with Step 9.3.
 */
final class CommissionController extends AbstractController
{
    public const CLIENT_HOME = 'home';

    public const CLIENT_PROJECT = 'project';

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly CommissionFileStorage $storage,
        #[Autowire(service: 'limiter.commission_enquiry')]
        private readonly RateLimiterFactoryInterface $limiter,
        // The debug toolbar's profiler; only present locally.
        #[Autowire(service: 'profiler')]
        private readonly ?Profiler $profiler = null,
    ) {
    }

    /**
     * ?for=home or ?for=project opens with that form chosen; each form is
     * sent to its own ?for= address, so only that form handles it.
     */
    #[Route(path: '/bespoke-commissions', name: 'commissions', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $forms = [
            self::CLIENT_HOME => $this->formFactory->createNamed('commission_home', CommissionHomeForm::class, null, [
                'action' => $this->generateUrl('commissions', [
                    'for' => self::CLIENT_HOME,
                ]),
            ]),
            self::CLIENT_PROJECT => $this->formFactory->createNamed('commission_project', CommissionProjectForm::class, null, [
                'action' => $this->generateUrl('commissions', [
                    'for' => self::CLIENT_PROJECT,
                ]),
            ]),
        ];
        $client = (string) $request->query->get('for', '');
        $client = isset($forms[$client]) ? $client : '';

        $submitted = false;
        if ($client !== '' && $request->isMethod('POST')) {
            $form = $forms[$client];
            if ($request->request->count() === 0 && $request->files->count() === 0) {
                // More was sent than PHP accepts at once (post_max_size), so
                // it arrives empty. Locally, the debug toolbar would try to
                // read the whole unread request into memory.
                $this->profiler?->disable();
            }

            $form->handleRequest($request);
            if (! $form->isSubmitted()) {
                // Symfony usually spots the oversize send itself (with the
                // forms' upload_max_size_message); when it can't, say the same.
                $form->submit(null, false);
                $form->addError(new FormError(CommissionFields::tooLargeMessage()));
            }

            $submitted = true;
            if ($form->isValid()) {
                // Spam bots fill in the hidden field: thank them, save nothing.
                if (trim((string) $form->get(CommissionFields::HONEYPOT)->getData()) !== '') {
                    return $this->redirectToRoute('commissions', [
                        'for' => $client,
                    ]);
                }

                if ($this->limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                    $this->save($client, $form);

                    // Step 9.3 adds the "Commission submitted" page and the emails.
                    return $this->redirectToRoute('commissions', [
                        'for' => $client,
                    ]);
                }

                $form->addError(new FormError("You've sent several enquiries in a short time. Please try again in an hour, or email us at info@lluniostudios.co.uk."));
            }
        }

        return $this->render('commissions/index.html.twig', [
            'homeForm' => $forms[self::CLIENT_HOME],
            'projectForm' => $forms[self::CLIENT_PROJECT],
            'client' => $client,
            'openRevealed' => $client !== '',
        ], new Response(status: $submitted ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Save the enquiry and its files. If saving fails, files already moved
     * into storage are removed again.
     */
    private function save(string $client, FormInterface $form): CommissionEnquiry
    {
        $text = static fn (string $field): ?string => ($value = trim((string) $form->get($field)->getData())) === '' ? null : $value;
        $enquiry = $client === self::CLIENT_HOME
            ? CommissionEnquiry::forHome(
                name: (string) $text('name'),
                email: (string) $text('email'),
                phone: $text('phone'),
                contactMethod: (string) $text('contactMethod'),
                idea: (string) $text('idea'),
            )
            : CommissionEnquiry::forProject(
                company: (string) $text('company'),
                contactName: (string) $text('contactName'),
                email: (string) $text('email'),
                phone: $text('phone'),
                projectType: (string) $text('projectType'),
                location: (string) $text('location'),
                project: $text('project'),
                integration: $text('integration'),
            );

        /** @var list<CommissionEnquiryFile> $stored */
        $stored = [];
        try {
            foreach ((array) $form->get('files')->getData() as $upload) {
                if ($upload instanceof UploadedFile) {
                    $file = $this->storage->store($upload);
                    $stored[] = $file;
                    $enquiry->addFile($file);
                }
            }

            $this->entityManager->persist($enquiry);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            foreach ($stored as $file) {
                $this->storage->remove($file);
            }

            throw $exception;
        }

        return $enquiry;
    }
}
