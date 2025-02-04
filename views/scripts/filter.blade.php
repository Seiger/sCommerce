@php use Seiger\sCommerce\Facades\sCommerce; @endphp
<script>
    document.addEventListener("click", function(e) {
        if (e.target) {
            switch(true) {
                case Boolean(e.target.closest('[data-sFilter]')?.hasAttribute("data-sFilter")):
                    dataFilter = e.target.closest('[data-sFilter]').getAttribute('data-sFilter');
                    generateFilterUrl(e, dataFilter);
                    break;
                case Boolean(e.target.closest('[data-sRange]')?.hasAttribute("data-sRange")):
                    dataRange = e.target.closest('[data-sRange]').getAttribute('data-sRange');
                    rangeBlocks = e.target.closest('[data-sRange]').querySelectorAll('input[type=\"number\"]');
                    rangeValues = Array.from(rangeBlocks).map(input => parseInt(input.value));
                    dataRange = dataRange + '=' + Math.min(...rangeValues) + ',' + Math.max(...rangeValues);
                    generateFilterUrl(e, dataRange, 'range');
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
                        // If the current value already exists, then delete it
                        valueArray = valueArray.filter(v => v !== filterValue);
                    } else if (type === 'checkbox') {
                        // If type 'checkbox' => add new value
                        valueArray.push(filterValue);
                    } else if (type === 'radio' || type === 'range') {
                        // If type 'radio' or 'range' => replace completely
                        valueArray = [filterValue];
                    } else {
                        valueArray.push(filterValue);
                    }

                    // Remove duplicates and convert back to key=val format
                    return valueArray.length > 0 ? `${key}=${[...new Set(valueArray)].join(',')}` : null;
                }
                return existingFilter;
            }).filter(Boolean);

            // If no such key is found, add a new one
            if (searchFilter) {
                existingFilters.push(dataFilter);
            }

            // Form a new URL
            const newPath = existingFilters.length > 0
                ? `${_path.replace(_filterMatch, '')}/${existingFilters.join(';')}/`
                : `${_path.replace(_filterMatch, '')}/`;
            currentUrl.pathname = newPath.replace('//', '/');
        } else {
            // If there were no filters before this
            currentUrl.pathname = `${_path}/${dataFilter}/`.replace('//', '/');
        }

        window.location.href = `${currentUrl.pathname}${_getParams}`;
    }
</script>