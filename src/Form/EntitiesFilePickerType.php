<?php

declare(strict_types=1);

namespace Pentatrion\UploadBundle\Form;

use Override;
use Pentatrion\UploadBundle\Service\FileManagerHelperInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class EntitiesFilePickerType extends AbstractType
{
    private string $locale;

    public function __construct(
        private readonly FileManagerHelperInterface $fileManagerHelper,
        private readonly RequestStack $requestStack,
        private readonly NormalizerInterface $normalizer
    ) {
        $this->locale = substr($requestStack->getCurrentRequest()->getLocale(), 0, 2);
    }

    /**
     * @throws ExceptionInterface
     */
    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $value = $form->getData();

        $fileManagerConfig = $this->fileManagerHelper->completeConfig($options['fileManagerConfig']);
        $fileManagerConfig['multiple'] = true;

        $view->vars['attr']['data-name'] = $view->vars['full_name'];
        $view->vars['attr']['data-minifilemanager'] = json_encode($fileManagerConfig);
        $view->vars['attr']['data-entity-form-file-picker'] = 'true';

        /* @deprecated */
        $view->vars['attr']['data-uploaded-files'] = json_encode($this->normalizer->normalize($value));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('fileManagerConfig');

        $resolver->setDefault('entry_type', UploadedFileType::class);
        $resolver->setDefault('allow_add', true);
        $resolver->setDefault('allow_delete', true);
        $resolver->setDefault('delete_empty', true);
    }

    #[Override]
    public function getParent(): ?string
    {
        return CollectionType::class;
    }
}
