<?php

namespace App\Controller;

use OpenTelemetry\API\Trace\SpanInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenTelemetry\SDK\Trace\TracerProvider;

class HelloController extends AbstractController
{
    private const TEMPLATE = 'hello/index.html.twig';

    private string $jaegerGuiUrl;
    private string $zipkinGuiUrl;

     public function __construct(TracerProvider $provider, string $jaegerGuiUrl, string $zipkinGuiUrl)
     {
         $this->jaegerGuiUrl = $jaegerGuiUrl;
         $this->zipkinGuiUrl = $zipkinGuiUrl;
         $this->tracer = $provider->getTracer('io.opentelemetry.contrib.php');
     }

     /**
     * @Route("/hello", name="hello")
     */
    public function index(): Response
    {
        $controllerSpan = $this->startSpan(__METHOD__);

        $controllerSpan->addEvent('Start doing stuff');
        // simulate some computation
        usleep(50000);
        $controllerSpan->addEvent('Finished doing stuff');

        $templateSpan = $this->startSpan('render:'.self::TEMPLATE);
        // render HTML
        $result = $this->render(self::TEMPLATE, [
            'jaeger_gui_url' => $this->jaegerGuiUrl,
            'zipkin_gui_url' => $this->zipkinGuiUrl,
            'trace_id' => $controllerSpan->getContext()->getTraceId(),
            'controller_span_id' => $controllerSpan->getContext()->getSpanId(),
            'template_span_id' => $templateSpan->getContext()->getSpanId(),
            'controller_sampling_decision' => $controllerSpan->isRecording() ? 'recording' : 'non-recording',
            'template_sampling_decision' => $templateSpan->isRecording() ? 'recording' : 'non-recording',
        ]);

        // end spans
        foreach ([$templateSpan, $controllerSpan] as $span) {
            $span->end();
        }

        // return rendered HTML
        return $result;
    }

    private function startSpan(string $name): SpanInterface
    {
        return $this->tracer
            ->spanBuilder($name)
            ->startSpan();
    }
}
