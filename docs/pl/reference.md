# Dokumentacja cen

`sPriceResolver` zapewnia spójne pola ceny we wszystkich powierzchniach sCommerce. Zdarzenia `sCommerce.ResolveProductPriceMode` i `sCommerce.ResolveProductPrice` są nadal obsługiwane.

Priorytet: cena osobista lub narastająca sPricing, potem promocyjna, potem bazowa. Stara cena jest ceną, od której zastosowano rabat.

## Zdarzenia

Pełne kontrakty i przykłady: [zdarzenia sCommerce (angielski)](https://seiger.github.io/sCommerce/developers/events/).

- `sCommerce.CheckoutValidationRules` — Reguły checkout przez referencję (`data`, `rules`): dodawanie, zastępowanie i usuwanie reguł.
- `sCommerce.ResolveProductPriceMode` — Tryb ceny (`product`, `optionId`, `priceMode`): pierwszy niepusty ciąg określa `auto` lub `wholesale`.
- `sCommerce.ResolveProductPrice` — Cena produktu (`product`, `optionId`, `priceMode`, `currency`, `pricing`): pierwsza odpowiedź liczbowa lub tablica nadpisuje cenę. sPricing nasłuchuje tego zdarzenia.

## sCommerce.ResolveCurrencies

Zdarzenie pozwala rozszerzyć, zastąpić lub przefiltrować słownik walut bez edytowania `config/currencies.php`.
Gdy pamięć podręczna jest pusta, sCommerce wczytuje słownik ISO, wywołuje kolejno nasłuchiwacze w kolejności rejestracji,
stosuje ustawienia projektu `sCommerce.currencies.<alpha>` i zapisuje pełną listę w pamięci podręcznej na około miesiąc.
Każdy nasłuchiwacz otrzymuje `['currencies' => Collection]` z wynikiem poprzedniego i zwraca nową Collection albo null.
Filtr `getCurrencies($where)` jest stosowany po zapisaniu listy w pamięci podręcznej.

Przykład implementacji nasłuchiwacza dodającego własną walutę:

```php
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
// Zarejestruj w dostawcy usług projektu przed pierwszym odczytem walut.
Event::listen('sCommerce.ResolveCurrencies', static function (array $payload): Collection {
    return $payload['currencies']->concat([[
        'name' => 'Euro Walraven',
        'alpha' => 'EUR_WAL',
        'numeric' => null,
        'symbol' => '€',
        'position' => 'before',
        'show' => 1,
        'thousands' => '&nbsp;',
        'decimals' => '.',
        'exp' => 2,
        'country' => [],
    ]]);
});
```

Ustawienia dotyczą również dodanych walut: na przykład `currencies.EUR_WAL.symbol` nadpisuje symbol zdefiniowany przez nasłuchiwacz.
Nasłuchiwacze są wykonywane tylko podczas tworzenia wspólnej pamięci podręcznej i nie mogą zależeć od użytkownika, sesji ani żądania.
Zarejestruj je przed pierwszym odczytem słownika. Nie czyść pamięci podręcznej przy każdym żądaniu ani wewnątrz nasłuchiwacza.
Po zmianie danych nasłuchiwacza, ustawień lub kodu użyj systemowego czyszczenia pamięci podręcznej.
Kolejne żądanie odbuduje słownik; już wczytana instancja zachowuje swoją listę w pamięci do końca żądania.

Zdarzenie rozszerza tylko słownik. Import cen we własnych walutach wymaga także obsługi ich kodów w walidacji API,
zapisie sPricing i przeliczaniu. Po wdrożeniu tego zdarzenia wyczyść dotychczasową pamięć podręczną walut,
aby nasłuchiwacze zostały wywołane podczas jej odbudowy.


## Przełącznik waluty w sklepie

Włącz waluty w ustawieniach Commerce i skonfiguruj kursy dla używanych par. Brakujący kurs ma wartość `1`. `getCurrencies()` zwraca słownik walut; przełącznik powinien korzystać z listy sklepu `basic.available_currencies`.

Dodaj tę trasę POST do pliku tras projektu ładowanego przez jego service provider. `/shop/currency` jest trasą projektu; sCommerce jej nie rejestruje. Middleware `web` zapewnia ochronę CSRF formularza. Zapisuj wybór w `$_SESSION`, z której odczytuje go sCommerce:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Seiger\sCommerce\Facades\sCommerce;

Route::middleware('web')->post('/shop/currency', function (Request $request) {
    $data = $request->validate([
        'currency' => ['required', 'string'],
    ]);

    $currency = strtoupper(trim($data['currency']));
    $allowed = (array) sCommerce::config('basic.available_currencies', []);

    abort_unless(in_array($currency, $allowed, true), 422);

    $_SESSION['currency'] = $currency;

    return back();
})->name('shop.currency');
```

Dodaj przełącznik do szablonu Blade:

```blade
<form method="POST" action="{{ route('shop.currency') }}">
    @csrf

    <select name="currency" onchange="this.form.submit()">
        @foreach((array) sCommerce::config('basic.available_currencies', []) as $code)
            <option value="{{ $code }}"
                @selected(sCommerce::currentCurrency() === $code)>
                {{ $code }}
            </option>
        @endforeach
    </select>

    <button type="submit">Zmień walutę</button>
</form>
```

Trasa sprawdza kod na liście włączonych walut sklepu, zapisuje go na czas bieżącej sesji i przekierowuje na poprzednią stronę. sCommerce odczytuje najpierw sesję, następnie cookie `currency`, a w pozostałych przypadkach używa `basic.main_currency`. Ten przykład nie tworzy trwałego cookie.

Zachowaj przekierowanie: `currentCurrency()` zapamiętuje walutę na czas bieżącego żądania. Następne żądanie wyświetla ceny i przelicza sumy koszyka w wybranej walucie. W implementacji AJAX przeładuj stronę po udanym zapisie zamiast generować ceny w tym samym żądaniu. Umieść przełącznik przed złożeniem zamówienia; nie zmienia on waluty już utworzonego zamówienia.
