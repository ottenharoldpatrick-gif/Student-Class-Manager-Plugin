# Student Class Manager Plugin

Een WordPress plugin voor het beheren van leerlingen en klassen met automatische pagina-aanmaak en login-redirection voor Sensei LMS.

## Beschrijving

Deze plugin is speciaal ontwikkeld voor onderwijsomgevingen die gebruikmaken van WordPress en Sensei LMS. De plugin maakt het mogelijk om leerlingen te organiseren in klassen, waarbij elke klas een eigen pagina krijgt waar leerlingen naartoe worden doorgestuurd na het inloggen.

## Functionaliteiten

### 🏫 Klasbeheer
- Aanmaken van nieuwe klassen met automatische pagina-generatie
- Bewerken van klassenpagina's via de standaard WordPress editor
- Verwijderen van klassen (inclusief bijbehorende pagina's)
- Overzicht van alle klassen met studentenaantallen

### 👥 Leerlingbeheer
- Individuele toewijzing van leerlingen aan klassen
- Bulk import van leerlingen via CSV-bestand
- Verplaatsen van leerlingen tussen klassen
- Klasveld bij nieuwe registraties

### 🔄 Automatische Redirects
- Leerlingen worden na inloggen automatisch doorgestuurd naar hun klassenpagina
- Leerlingen zonder klassentoewijzing gaan naar de homepage
- Integreert naadloos met het bestaande WordPress/Sensei login systeem

### 📊 Beheeroverzicht
- Dashboard met alle klassen en studentenaantallen
- Overzicht van alle leerlingen en hun huidige klassentoewijzingen
- Eenvoudige bulk acties voor efficiënt beheer

## Installatie

### Stap 1: Plugin Bestanden
1. Download de plugin bestanden
2. Maak een nieuwe map aan: `/wp-content/plugins/student-class-manager/`
3. Plaats het bestand `student-class-manager.php` in deze map

### Stap 2: Activatie
1. Ga naar je WordPress admin dashboard
2. Navigeer naar **Plugins** → **Geïnstalleerde Plugins**
3. Zoek "Student Class Manager" en klik op **Activeren**

### Stap 3: Database Setup
De plugin maakt automatisch een database tabel aan bij activatie. Er zijn geen verdere configuratiestappen nodig.

## Gebruik

### Admin Menu
Na installatie verschijnt er een nieuw menu-item **"Student Classes"** in je WordPress admin met de volgende submenu's:

#### 📚 Manage Classes
- **Nieuwe klas toevoegen**: Voer een klasnaam in en er wordt automatisch een pagina aangemaakt
- **Bestaande klassen**: Overzicht met links naar pagina-editor en voorbeeldweergave
- **Verwijderen**: Klassen kunnen worden verwijderd (inclusief bijbehorende pagina)

#### 👤 Assign Students  
- **Individuele toewijzing**: Selecteer een leerling en wijs deze toe aan een klas
- **Overzicht toewijzingen**: Zie alle leerlingen en hun huidige klassentoewijzingen
- **Bewerken**: Directe links naar gebruikersprofielen voor aanpassingen

#### 📥 Bulk Import
- **CSV Upload**: Importeer meerdere leerlingen tegelijk
- **Automatische accounts**: Genereert gebruikersaccounts en wachtwoorden
- **Klassentoewijzing**: Alle geïmporteerde leerlingen worden direct aan de gekozen klas toegewezen

### CSV Import Format

Voor bulk import gebruik je het volgende CSV format (één leerling per regel):

```
gebruikersnaam,email,volledige_naam
jan.jansen,jan@school.nl,Jan Jansen
marie.pietersen,marie@school.nl,Marie Pietersen
lisa.de.vries,lisa@school.nl,Lisa de Vries
```

**Belangrijke opmerkingen:**
- Gebruikersnamen moeten uniek zijn
- Email adressen moeten uniek zijn  
- Wachtwoorden worden automatisch gegenereerd
- Alle geïmporteerde leerlingen krijgen de rol 'subscriber'

### Registratieformulier

Wanneer nieuwe leerlingen zich registreren via het WordPress registratieformulier, verschijnt er automatisch een dropdown-menu waar ze hun klas kunnen selecteren. Dit veld is verplicht.

### Gebruikersprofielen

In elk gebruikersprofiel (zowel admin als zelf-bewerking) verschijnt er een sectie "Klasinformatie" waar de klassentoewijzing kan worden bekeken en aangepast.

## Technische Details

### Database Schema
De plugin maakt een nieuwe tabel aan: `wp_student_classes`

```sql
CREATE TABLE wp_student_classes (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    class_name varchar(100) NOT NULL,
    page_id bigint(20) DEFAULT NULL,
    created_date datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY class_name (class_name)
);
```

### User Meta
Klassentoewijzingen worden opgeslagen als user meta:
- **Meta Key**: `student_class`
- **Meta Value**: Klasnaam (string)

### Hooks & Filters
De plugin gebruikt de volgende WordPress hooks:
- `login_redirect` - Voor automatische doorverwijzing na login
- `register_form` - Voor klasveld in registratieformulier  
- `show_user_profile` / `edit_user_profile` - Voor klasveld in gebruikersprofiel
- `wp_ajax_*` - Voor AJAX functionaliteiten in admin

## Vereisten

- **WordPress**: 5.0 of hoger
- **PHP**: 7.4 of hoger
- **Sensei LMS**: Aanbevolen (maar niet strikt vereist)
- **Gebruikersrollen**: Plugin werkt met standaard WordPress rollen (subscriber voor leerlingen)

## Compatibiliteit

- ✅ WordPress 5.0+
- ✅ Sensei LMS
- ✅ Standaard WordPress themes
- ✅ Gutenberg editor
- ✅ Classic editor

## Beveiliging

- Alle formulieren zijn beveiligd met WordPress nonces
- Input wordt gesanitized volgens WordPress standaarden
- Alleen administrators kunnen klassen beheren
- Gebruikers kunnen alleen hun eigen klasinformatie zien

## Support & Ontwikkeling

### Troubleshooting
1. **Plugin activeert niet**: Controleer PHP versie en WordPress versie
2. **Klassen verschijnen niet**: Controleer database permissies
3. **Redirect werkt niet**: Controleer of leerlingen de rol 'subscriber' hebben
4. **CSV import faalt**: Controleer CSV format en unieke emailadressen

### Aanpassingen
De plugin is modulair opgebouwd en kan eenvoudig worden uitgebreid met:
- Extra gebruikersvelden
- Aangepaste redirect logica  
- Integratie met andere LMS plugins
- Rapportage functionaliteiten

## Changelog

### Versie 1.0.0
- Initiële release
- Basis klasbeheer functionaliteit
- Automatische pagina-aanmaak
- Bulk import via CSV
- Login redirect systeem
- Admin interface

## Licentie

GPL v2 or later

## Auteur

Ontwikkeld voor eco.isdigitaal.nl - Economics onderwijsondersteuning door meneer Otten.

---

Voor vragen of ondersteuning, neem contact op via de WordPress admin interface of bekijk de plugin instellingen in je dashboard.