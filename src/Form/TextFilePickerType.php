<?php

declare(strict_types=1);

namespace Pentatrion\UploadBundle\Form;

use Override;
use Pentatrion\UploadBundle\Service\FileManagerHelperInterface;
use Pentatrion\UploadBundle\Service\UploadedFileHelperInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TextFilePickerType extends AbstractType
{
    public function __construct(
        private readonly FileManagerHelperInterface $fileManagerHelper,
        private readonly UploadedFileHelperInterface $uploadedFileHelper,
        RequestStack $requestStack,
        private readonly NormalizerInterface $normalizer
    ) {}

    /**
     * @throws ExceptionInterface
     */
    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $value = $form->getData();

        $fileManagerConfig = $this->fileManagerHelper->completeConfig($options['fileManagerConfig']);

        $uploadedFiles = [];
        if (!empty($value)) {
            $values = explode(',', (string) $value);
            foreach ($values as $fileRelativePath) {
                $uploadedFiles[] = $this->uploadedFileHelper->getUploadedFile(
                    $fileRelativePath,
                    $fileManagerConfig['entryPoints'][0]['origin']
                );
            }
        }

        $view->vars['type'] = 'hidden';
        $view->vars['attr']['data-minifilemanager'] = json_encode($fileManagerConfig);
        $view->vars['attr']['data-uploaded-files'] = json_encode($this->normalizer->normalize($uploadedFiles));
        $view->vars['attr']['data-text-form-file-picker'] = 'true';
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('fileManagerConfig');
    }

    #[Override]
    public function getParent(): ?string
    {
        return TextType::class;
    }
}
