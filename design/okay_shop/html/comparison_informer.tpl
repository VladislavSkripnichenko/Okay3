{* Compaison informer (given by Ajax) *}
{if $comparison->products|count > 0}
    <a class="header_informers__link d-flex align-items-center" href="{url_generator route="comparison"}">
        {*include file="svg.tpl" svgId="compare_icon"*}
        <i class="icon icon-balance-scale"></i>
        {*<span class="informer_name tablet-hidden" data-language="index_comparison">{$lang->index_comparison}</span>*}
        <span class="compare_counter">{$comparison->products|count}</span>
    </a>
{else}
    <div class="header_informers__link d-flex align-items-center">
        {*include file="svg.tpl" svgId="compare_icon"*}
        <i class="icon icon-balance-scale"></i>
        {*<span class="informer_name tablet-hidden" data-language="index_comparison">{$lang->index_comparison}</span>*}
    </div>
{/if}
