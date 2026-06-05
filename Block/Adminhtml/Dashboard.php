<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Block\Adminhtml;

use Etechflow\SeoAudit\Model\Scanner;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Phrase;

/**
 * Renders the SEO Audit score + summary cards and a "Run Scan" button.
 * Output via _toHtml() (no .phtml) to avoid the production preprocessed-template
 * step — same pattern as the rest of the Etechflow SEO suite. The severity/area
 * counts are clickable and filter the issue grid below.
 */
class Dashboard extends Template
{
    private const LISTING = 'etechflow_seoaudit_issue_listing';

    public function __construct(
        Context $context,
        private readonly Scanner $scanner,
        private readonly \Magento\Framework\View\Helper\SecureHtmlRenderer $secureRenderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        $s = $this->scanner->getLastSummary();
        $scanUrl = $this->getUrl('seoaudit/index/scan');
        $btn = '<a href="' . $this->escapeUrl($scanUrl) . '" class="action-primary" style="display:inline-block;padding:9px 18px;border-radius:4px;text-decoration:none;">'
            . __('Run SEO Scan Now') . '</a>';

        if (!$s) {
            return '<div style="padding:20px;background:#fff;border:1px solid #e3e3e3;border-radius:6px;margin-bottom:20px">'
                . '<h2 style="margin-top:0">' . __('SEO Audit') . '</h2>'
                . '<p>' . __('No scan has run yet. Run your first audit to see your store\'s SEO health score and issues.') . '</p>'
                . $btn . '</div>';
        }

        $score = (int) ($s['score'] ?? 0);
        $color = $score >= 80 ? '#1a7f37' : ($score >= 50 ? '#b8860b' : '#c0392b');
        $sev = $s['by_severity'] ?? [];
        $cat = $s['by_category'] ?? [];

        $html  = '<div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px">';
        $html .= '<div style="flex:0 0 200px;padding:20px;background:#fff;border:1px solid #e3e3e3;border-radius:6px;text-align:center">'
            . '<div style="font-size:54px;font-weight:700;line-height:1;color:' . $color . '">' . $score . '</div>'
            . '<div style="color:#777;margin-top:4px">' . __('SEO Health Score') . ' / 100</div>'
            . '<div style="margin-top:14px">' . $btn . '</div>'
            . '</div>';
        $html .= '<div style="flex:1;min-width:220px;padding:20px;background:#fff;border:1px solid #e3e3e3;border-radius:6px">'
            . '<h3 style="margin-top:0">' . __('Issues by severity') . '</h3>'
            . $this->row(__('Critical'), (int)($sev['critical'] ?? 0), '#c0392b', ['severity' => 'critical'])
            . $this->row(__('Warning'), (int)($sev['warning'] ?? 0), '#b8860b', ['severity' => 'warning'])
            . $this->row(__('Notice'), (int)($sev['notice'] ?? 0), '#777', ['severity' => 'notice'])
            . '<div style="margin-top:8px;color:#777;font-size:12px">' . __('%1 checks run · %2 total issues', (int)($s['checks'] ?? 0), (int)($s['total'] ?? 0)) . '</div>'
            . '</div>';
        $html .= '<div style="flex:1;min-width:220px;padding:20px;background:#fff;border:1px solid #e3e3e3;border-radius:6px">'
            . '<h3 style="margin-top:0">' . __('Issues by area') . '</h3>';
        foreach (['meta' => __('Meta tags'), 'content' => __('Content'), 'links' => __('Links'), 'schema' => __('Structured data')] as $k => $label) {
            $html .= $this->row($label, (int)($cat[$k] ?? 0), '#3b5998', ['category' => $k]);
        }
        $html .= '<div style="margin-top:8px;color:#999;font-size:11px">' . __('Tip: click a count to filter the list below.') . '</div>';
        $html .= '</div></div>';

        return $html . $this->filterScript();
    }

    /**
     * @param array<string,string> $filter
     */
    private function row(Phrase|string $label, int $n, string $color, array $filter = []): string
    {
        $attr = $filter ? ' data-seoaudit-filter=\'' . $this->escapeHtmlAttr(json_encode($filter)) . '\' class="seoaudit-clickable"' : '';
        $hover = $filter ? 'cursor:pointer;' : '';
        return '<div' . $attr . ' style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f0f0f0;' . $hover . '">'
            . '<span>' . $this->escapeHtml($label) . '</span>'
            . '<strong style="color:' . $color . '">' . $n . '</strong></div>';
    }

    private function filterScript(): string
    {
        $ns = self::LISTING;
        $css = '.seoaudit-clickable:hover{background:#f6f6f6}';
        $js = <<<JS
require(['uiRegistry'], function (registry) {
    var ns = '{$ns}';
    var paths = [ns + '.' + ns + '.listing_top.listing_filters', ns + '.listing_top.listing_filters'];
    document.querySelectorAll('[data-seoaudit-filter]').forEach(function (el) {
        el.addEventListener('click', function () {
            var f;
            try { f = JSON.parse(el.getAttribute('data-seoaudit-filter')); } catch (e) { return; }
            paths.forEach(function (name) {
                registry.get(name, function (filters) {
                    try { filters.set("filters", f); filters.apply(); } catch (e) {}
                });
            });
            var grid = document.querySelector('.admin__data-grid-outer-wrap') || document.querySelector('[data-role="grid-wrapper"]');
            if (grid) { grid.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
    });
});
JS;
        return $this->secureRenderer->renderTag('style', [], $css, false)
            . $this->secureRenderer->renderTag('script', [], $js, false);
    }
}
