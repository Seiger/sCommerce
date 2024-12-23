@php use Seiger\sCommerce\Facades\sCommerce; @endphp
<script>
    document.addEventListener("click", function(e) {
        if (e.target) {
            switch(true) {
                case Boolean(e.target.closest('[data-sFilter]')?.hasAttribute("data-sFilter")):
                    dataFilter = e.target.closest('[data-sFilter]').getAttribute('data-sFilter');
                    generateFilterUrl(e, dataFilter);
                    break;
            }
        }
    });
    function generateFilterUrl(e, dataFilter, type = 'checkbox') {
        let newFilters = [];
        let searchFilter = true;

        const suffix = '{{evo()->getConfig('friendly_url_suffix', '')}}';
        const currentUrl = new URL(window.location.href);
        const _path = currentUrl.pathname.substring(0, currentUrl.pathname.length - suffix.length);
        const _getParams = currentUrl.search;
        const _filterMatch = '{{evo()->getPlaceholder('sFilters')}}';

        if (_filterMatch) {
            let existingFilters = _filterMatch.split(';');
            const [filterKey, filterValue] = dataFilter.split('=');

            existingFilters = existingFilters.map(existingFilter => {
                const [key, values] = existingFilter.split('=');
                if (key === filterKey) {
                    searchFilter = false;
                    let valueArray = values.split(',');

                    if (valueArray.includes(filterValue)) {
                        valueArray = valueArray.filter(v => v !== filterValue);
                    } else if (type !== 'radio' && filterKey !== 'price') {
                        valueArray.push(filterValue);
                    } else {
                        valueArray = [filterValue];
                    }

                    return valueArray.length > 0 ? `${key}=${[...new Set(valueArray)].join(',')}` : null;
                }
                return existingFilter;
            }).filter(Boolean);

            if (searchFilter) {
                existingFilters.push(dataFilter);
            }

            const newPath = existingFilters.length > 0
                ? `${_path.replace(_filterMatch, '')}/${existingFilters.join(';')}/`
                : `${_path.replace(_filterMatch, '')}/`;
            currentUrl.pathname = newPath.replace('//', '/');
        } else {
            currentUrl.pathname = `${_path}/${dataFilter}/`.replace('//', '/');
        }

        window.location.href = `${currentUrl.pathname}${_getParams}`;
    }
</script>