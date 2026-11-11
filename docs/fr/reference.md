# Référence des prix

`sPriceResolver` fournit des champs cohérents. Les événements `sCommerce.ResolveProductPriceMode` et `sCommerce.ResolveProductPrice` restent compatibles.

Priorité: prix personnel ou cumulatif sPricing, prix promotionnel, prix de base. L'ancien prix est la base de la réduction.

## Événements

Contrats complets et exemples : [événements sCommerce (anglais)](https://seiger.github.io/sCommerce/developers/events/).

- `sCommerce.CheckoutValidationRules` — Règles du checkout par référence (`data`, `rules`) : ajout, remplacement ou suppression.
- `sCommerce.ResolveProductPriceMode` — Mode de prix (`product`, `optionId`, `priceMode`) : la première chaîne non vide détermine `auto` ou `wholesale`.
- `sCommerce.ResolveProductPrice` — Prix du produit (`product`, `optionId`, `priceMode`, `currency`, `pricing`) : la première réponse numérique ou le premier tableau remplace le prix. sPricing écoute cet événement.

## sCommerce.ResolveCurrencies

Cet événement permet d’étendre, de remplacer ou de filtrer le répertoire des devises sans modifier `config/currencies.php`.
Lorsque le cache est vide, sCommerce charge le répertoire ISO, appelle les écouteurs dans leur ordre d’enregistrement,
applique les paramètres du projet `sCommerce.currencies.<alpha>` et met la liste complète en cache pendant environ un mois.
Chaque écouteur reçoit `['currencies' => Collection]` avec le résultat du précédent et renvoie une nouvelle Collection ou null.
Le filtre `getCurrencies($where)` est appliqué après la mise en cache.

Exemple d’implémentation d’un écouteur qui ajoute une devise personnalisée :

```php
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
// Enregistrer dans le fournisseur de services du projet avant le premier accès aux devises.
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

Les paramètres s’appliquent aussi aux devises ajoutées : par exemple, `currencies.EUR_WAL.symbol` remplace le symbole défini par l’écouteur.
Les écouteurs ne sont exécutés que lors de la construction du cache partagé et ne doivent pas dépendre de l’utilisateur, de la session ou de la requête.
Enregistrez-les avant le premier accès au répertoire. Ne videz pas le cache à chaque requête ni dans un écouteur.
Après une modification des données de l’écouteur, des paramètres ou du code, utilisez le nettoyage du cache système.
La requête suivante reconstruit le répertoire ; une instance déjà chargée conserve sa liste en mémoire jusqu’à la fin de la requête.

Cet événement étend uniquement le répertoire. Pour importer des prix dans des devises personnalisées, leurs codes doivent aussi
être pris en charge par la validation de l’API, le stockage sPricing et la conversion. Après l’installation de cet événement,
videz l’ancien cache des devises afin que les écouteurs soient appelés lors de sa reconstruction.


## Sélecteur de devise sur le site

Activez les devises dans les paramètres de Commerce et configurez les taux pour les paires utilisées. Un taux absent vaut `1`. `getCurrencies()` renvoie le répertoire des devises ; le sélecteur doit utiliser la liste du magasin `basic.available_currencies`.

Ajoutez cette route POST au fichier de routes de votre projet chargé par son fournisseur de services. `/shop/currency` est une route du projet ; sCommerce ne l’enregistre pas. Le middleware `web` assure la protection CSRF du formulaire. Enregistrez le choix dans `$_SESSION`, que sCommerce consulte :

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

Ajoutez le sélecteur à votre modèle Blade :

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

    <button type="submit">Changer de devise</button>
</form>
```

La route vérifie le code dans la liste des devises activées du magasin, le conserve pour la session en cours et redirige vers la page précédente. sCommerce consulte d’abord la session, puis le cookie `currency`, et utilise sinon `basic.main_currency`. Cet exemple ne crée pas de cookie persistant.

Conservez la redirection : `currentCurrency()` met la devise en cache pour la requête en cours. La requête suivante affiche les prix et recalcule les totaux du panier dans la devise choisie. Pour une implémentation AJAX, rechargez la page après l’enregistrement au lieu de calculer les prix dans la même requête. Placez le sélecteur avant la validation de la commande ; il ne modifie pas la devise d’une commande déjà créée.
