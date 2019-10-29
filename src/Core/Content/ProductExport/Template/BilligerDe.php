<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Template;

use Shopware\Core\Content\ProductExport\ProductExportEntity;

class BilligerDe extends AbstractTemplate
{
    public function __construct()
    {
        $this->name = 'billiger_de';
        $this->translationKey = 'sw-sales-channel.detail.productComparison.templates.template-label.billiger-de';
        $this->headerTemplate = 'aid,{#- -#}
brand,{#- -#}
mpnr,{#- -#}
ean,{#- -#}
name,{#- -#}
desc,{#- -#}
shop_cat,{#- -#}
price,{#- -#}
ppu,{#- -#}
link,{#- -#}
image,{#- -#}
dlv_time,{#- -#}
dlv_cost,{#- -#}
pzn,{#- -#}
unit_pricing_measure,{#- -#}
unit_pricing_base_measure,{#- -#}
target_url,{#- -#}
images{#- -#}';
        $this->bodyTemplate = '"{{ product.productNumber }}",{#- -#}
"{{ product.manufacturer.translated.name }}",{#- -#}
"{{ product.manufacturerNumber }}",{#- -#}
"{{ product.ean }}",{#- -#}
"{{ product.translated.name|length > 80 ? product.translated.name|slice(0, 80) ~ \'...\' : product.translated.name }}",{#- -#}
"{{ product.translated.description|raw|length > 900 ? product.translated.description|raw|slice(0,900) ~ \'...\' : product.translated.description|raw }}{#- -#}
",{#- -#}
"{{ product.categories.first.getBreadCrumb|slice(1)|join(\' > \')|raw }}",{#- -#}
"{{ product.calculatedListingPrice.from.unitPrice }}",{#- -#}
{% set price = product.calculatedListingPrice.from %}
"{% if price.referencePrice is not null %}
{{ price.referencePrice.price|currency }} / {{ price.referencePrice.referenceUnit }} {{ price.referencePrice.unitName }}{#- -#}
{% endif %}",{#- -#}
"{{ seoUrl(\'frontend.detail.page\', {\'productId\': product.id}) }}",{#- -#}
"{{ product.cover.media.url }}",{#- -#}
"{% if product.availableStock >= product.minPurchase and product.deliveryTime %}
{{ "detail.deliveryTimeAvailable"|trans({\'%name%\': product.deliveryTime.translation(\'name\')}) }}{#- -#}
{% elseif product.availableStock < product.minPurchase and product.deliveryTime and product.restockTime %}
{{ "detail.deliveryTimeRestock"|trans({\'%restockTime%\': product.restockTime,\'%name%\': product.deliveryTime.translation(\'name\')}) }}{#- -#}
{% else %}
{{ "detail.soldOut"|trans }}{#- -#}
{% endif %}",{#- -#}
"4.95",{# change your default delivery costs #}{#- -#}
,{#- -#}
"{% if product.purchaseUnit %}{{ product.purchaseUnit }} {{ product.unit.shortCode }}{% endif %}",{#- -#}
"{% if product.referenceUnit %}{{ product.referenceUnit }} {{ product.unit.shortCode }}{% endif %}",{#- -#}
"{{ seoUrl(\'frontend.detail.page\', {\'productId\': product.id}) }}",{#- -#}
{% if product.media|length > 1 %}"{% for mediaAssociation in product.media|slice(0, 5) %}{{ mediaAssociation.media.url }}{% if not loop.last %},{% endif %}{% endfor %}"{% endif %}{#- -#}';
        $this->footerTemplate = '';
        $this->fileName = 'billiger.csv';
        $this->encoding = ProductExportEntity::ENCODING_UTF8;
        $this->fileFormat = ProductExportEntity::FILE_FORMAT_CSV;
        $this->generateByCronjob = false;
        $this->interval = 86400;
    }
}
