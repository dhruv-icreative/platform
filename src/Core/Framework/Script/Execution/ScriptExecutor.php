<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Script\Debugging\Debug;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Script\Exception\NoHookServiceFactoryException;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Twig\Environment;
use Twig\Extension\DebugExtension;

class ScriptExecutor
{
    public static bool $isInScriptExecutionContext = false;

    private LoggerInterface $logger;

    private ScriptLoader $loader;

    private ScriptTraces $traces;

    private ContainerInterface $container;

    private TranslationExtension $translationExtension;

    /**
     * @psalm-suppress ContainerDependency
     */
    public function __construct(
        ScriptLoader $loader,
        LoggerInterface $logger,
        ScriptTraces $traces,
        ContainerInterface $container,
        TranslationExtension $translationExtension
    ) {
        $this->logger = $logger;
        $this->loader = $loader;
        $this->traces = $traces;
        $this->container = $container;
        $this->translationExtension = $translationExtension;
    }

    public function execute(Hook $hook): void
    {
        if ($hook instanceof InterfaceHook) {
            throw new \RuntimeException(sprintf(
                'Tried to execute InterfaceHook "%s", butInterfaceHooks should not be executed, execute the functions of the hook instead',
                \get_class($hook)
            ));
        }

        $scripts = $this->loader->get($hook->getName());
        $this->traces->initHook($hook);

        foreach ($scripts as $script) {
            try {
                static::$isInScriptExecutionContext = true;
                $this->render($hook, $script);
            } catch (\Throwable $e) {
                $scriptException = new ScriptExecutionFailedException($hook->getName(), $script->getName(), $e);
                $this->logger->error($scriptException->getMessage(), ['exception' => $e]);

                throw $scriptException;
            } finally {
                static::$isInScriptExecutionContext = false;
            }

            if ($hook instanceof StoppableHook && $hook->isPropagationStopped()) {
                break;
            }
        }
    }

    private function render(Hook $hook, Script $script): void
    {
        $twig = $this->initEnv($script);

        $services = $this->initServices($hook, $script);

        $twig->addGlobal('services', $services);

        $this->traces->trace($hook, $script, function (Debug $debug) use ($twig, $script, $hook): void {
            $twig->addGlobal('debug', $debug);

            $template = $twig->load($script->getName());

            if (!$hook instanceof FunctionHook) {
                $template->render(['hook' => $hook]);

                return;
            }

            $blockName = $hook->getFunctionName();
            if ($template->hasBlock($blockName)) {
                $template->renderBlock($blockName, ['hook' => $hook]);

                return;
            }

            if (!$hook instanceof OptionalFunctionHook) {
                throw new \RuntimeException(sprintf(
                    'Required function "%s" missing in script "%s", please make sure you add the required block in your script.',
                    $hook->getFunctionName(),
                    $script->getName()
                ));
            }
        });

        $this->callAfter($services, $hook, $script);
    }

    private function initEnv(Script $script): Environment
    {
        $twig = new TwigEnvironment(
            new ScriptTwigLoader($script),
            $script->getTwigOptions()
        );

        $twig->addExtension(new PhpSyntaxExtension());
        $twig->addExtension($this->translationExtension);

        if ($script->getTwigOptions()['debug'] ?? false) {
            $twig->addExtension(new DebugExtension());
        }

        return $twig;
    }

    private function initServices(Hook $hook, Script $script): array
    {
        $services = [];
        foreach ($hook->getServiceIds() as $serviceId) {
            if (!$this->container->has($serviceId)) {
                throw new ServiceNotFoundException($serviceId, 'Hook: ' . $hook->getName());
            }

            $service = $this->container->get($serviceId);
            if (!$service instanceof HookServiceFactory) {
                throw new NoHookServiceFactoryException($serviceId);
            }

            $services[$service->getName()] = $service->factory($hook, $script);
        }

        return $services;
    }

    private function callAfter(array $services, Hook $hook, Script $script): void
    {
        foreach ($hook->getServiceIds() as $serviceId) {
            if (!$this->container->has($serviceId)) {
                throw new ServiceNotFoundException($serviceId, 'Hook: ' . $hook->getName());
            }

            $factory = $this->container->get($serviceId);
            if (!$factory instanceof HookServiceFactory) {
                throw new NoHookServiceFactoryException($serviceId);
            }

            $service = $services[$factory->getName()];

            $factory->after($service, $hook, $script);
        }
    }
}
