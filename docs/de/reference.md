# Preisreferenz

`sPriceResolver` liefert einheitliche Preisfelder. Die Ereignisse `sCommerce.ResolveProductPriceMode` und `sCommerce.ResolveProductPrice` werden weiterhin unterstützt.

Priorität: persönliche oder kumulative sPricing-Preis, Aktionspreis, Basispreis. Der alte Preis ist die Preisbasis des Rabatts.

## Ereignisse

Vollständige Verträge und Beispiele: [sCommerce-Ereignisse (Englisch)](https://seiger.github.io/sCommerce/developers/events/).

- `sCommerce.CheckoutValidationRules` — Checkout-Regeln als Referenz (`data`, `rules`): Regeln hinzufügen, ersetzen oder entfernen.
- `sCommerce.ResolveProductPriceMode` — Preismodus (`product`, `optionId`, `priceMode`): Die erste nicht leere Zeichenfolge bestimmt `auto` oder `wholesale`.
- `sCommerce.ResolveProductPrice` — Produktpreis (`product`, `optionId`, `priceMode`, `currency`, `pricing`): Die erste numerische Antwort oder das erste Array überschreibt den Preis. sPricing verwendet dieses Ereignis.

## sCommerce.ResolveCurrencies

Das Ereignis erweitert, ersetzt oder filtert das Währungsverzeichnis, ohne `config/currencies.php` zu bearbeiten.
Bei einem Cache-Miss lädt sCommerce das ISO-Verzeichnis, ruft die Listener in der Reihenfolge ihrer Registrierung auf,
wendet die Projekteinstellungen `sCommerce.currencies.<alpha>` an und speichert die vollständige Liste für etwa einen Monat im Cache.
Jeder Listener erhält `['currencies' => Collection]` mit dem Ergebnis des vorherigen Listeners und gibt eine neue Collection oder null zurück.
Der Filter `getCurrencies($where)` wird nach dem Caching angewendet.

Beispiel eines Listeners, der eine eigene Währung hinzufügt:

```php
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
// Im Service Provider des Projekts vor dem ersten Währungszugriff registrieren.
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

Die Einstellungen gelten auch für hinzugefügte Währungen: `currencies.EUR_WAL.symbol` überschreibt beispielsweise das Symbol des Listeners.
Listener werden nur beim Aufbau des gemeinsamen Caches ausgeführt und dürfen nicht vom Benutzer, der Sitzung oder der Anfrage abhängen.
Registrieren Sie sie vor dem ersten Zugriff auf das Währungsverzeichnis. Leeren Sie den Cache nicht bei jeder Anfrage oder innerhalb eines Listeners.
Verwenden Sie nach Änderungen an Listener-Daten, Einstellungen oder Code die systemweite Cache-Bereinigung.
Die nächste Anfrage baut das Verzeichnis neu auf; eine bereits geladene Instanz behält ihre Liste bis zum Ende der Anfrage im Speicher.

Dieses Ereignis erweitert nur das Verzeichnis. Für den Preisimport in eigenen Währungen müssen deren Codes zusätzlich
von der API-Validierung, der Speicherung in sPricing und der Umrechnung unterstützt werden. Leeren Sie nach der Installation
des Ereignisses den bisherigen Währungs-Cache, damit die Listener beim nächsten Aufbau ausgeführt werden.


## Währungsumschalter im Shop

Aktivieren Sie die Währungen in den Commerce-Einstellungen und konfigurieren Sie die Kurse für die benötigten Paare. Bei einem fehlenden Kurs wird `1` verwendet. `getCurrencies()` liefert das Währungsverzeichnis; verwenden Sie für den Umschalter die Shop-Liste `basic.available_currencies`.

Fügen Sie diese POST-Route in die vom Service Provider geladene Routendatei Ihres Projekts ein. `/shop/currency` ist eine Projektroute; sCommerce registriert sie nicht. Die Middleware `web` stellt den CSRF-Schutz des Formulars bereit. Speichern Sie die Auswahl in `$_SESSION`, die sCommerce ausliest:

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

Fügen Sie den Umschalter in Ihre Blade-Vorlage ein:

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

    <button type="submit">Währung ändern</button>
</form>
```

Die Route prüft den Code anhand der aktivierten Shop-Währungen, speichert ihn für die aktuelle Sitzung und leitet zurück. sCommerce liest zuerst die Sitzung, dann das Cookie `currency` und verwendet andernfalls `basic.main_currency`. Dieses Beispiel erstellt kein dauerhaftes Cookie.

Behalten Sie die Weiterleitung bei: `currentCurrency()` speichert die Währung für die aktuelle Anfrage zwischen. Die nächste Anfrage zeigt Preise und berechnet die Warenkorbsummen in der gewählten Währung neu. Laden Sie bei einer AJAX-Implementierung die Seite nach erfolgreichem Speichern neu, statt Preise in derselben Anfrage zu rendern. Platzieren Sie den Umschalter vor dem Bestellabschluss; die Währung einer bereits erstellten Bestellung ändert er nicht.
