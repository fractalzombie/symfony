<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Mime;

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Message;
use Twig\Environment;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class BodyRenderer implements BodyRendererInterface
{
    private Environment $twig;
    private array $context;
    private HtmlConverter $converter;

    public function __construct(Environment $twig, array $context = [])
    {
        $this->twig = $twig;
        $this->context = $context;
        if (class_exists(HtmlConverter::class)) {
            $this->converter = new HtmlConverter([
                'hard_break' => true,
                'strip_tags' => true,
                'remove_nodes' => 'head style',
            ]);
        }
    }

    public function render(Message $message): void
    {
        if (!$message instanceof TemplatedEmail) {
            return;
        }

        if (null === $message->getTextTemplate() && null === $message->getHtmlTemplate()) {
            // email has already been rendered
            return;
        }

        $messageContext = $message->getContext();

        if (isset($messageContext['email'])) {
            throw new InvalidArgumentException(sprintf('A "%s" context cannot have an "email" entry as this is a reserved variable.', get_debug_type($message)));
        }

        $vars = array_merge($this->context, $messageContext, [
            'email' => new WrappedTemplatedEmail($this->twig, $message),
        ]);

        if ($template = $message->getTextTemplate()) {
            $message->text($this->twig->render($template, $vars));
            $message->textTemplate(null);
        }

        if ($template = $message->getHtmlTemplate()) {
            $message->html($this->twig->render($template, $vars));
            $message->htmlTemplate(null);
        }

        $message->context([]);

        // if text body is empty, compute one from the HTML body
        if (!$message->getTextBody() && null !== $html = $message->getHtmlBody()) {
            $message->text($this->convertHtmlToText(\is_resource($html) ? stream_get_contents($html) : $html));
        }
    }

    private function convertHtmlToText(string $html): string
    {
        if (isset($this->converter)) {
            return $this->converter->convert($html);
        }

        return strip_tags(preg_replace('{<(head|style)\b.*?</\1>}is', '', $html));
    }
}
