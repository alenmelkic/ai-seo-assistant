# SEO AI Assistant — WordPress Plugin

**Plan dokument za Claude Code implementaciju**

Verzija: 1.0
Datum: Maj 2026
Target WP verzija: 6.5+ (sa optimizacijom za WP 7.0)

---

## 1. Pregled

WordPress plugin koji prilikom prve objave članka pokreće AI review modal sa dva koraka — content quality check + SEO/AEO generisanje. Plugin radi i na klasičnim WP sajtovima i u headless setup-u (dual rendering). Koristi WP 7.0 AI Client API sa fallback-om za starije verzije. Yoast i Rank Math su podržani od starta kroz adapter pattern.

### Ključni principi

1. **Editor je gospodar** — AI predlaže, editor odlučuje. Nikad ništa nije auto-applied bez ljudske potvrde.
2. **Prva objava only** — Modal se otvara samo kod prve `publish` akcije. Update-i preskaču modal osim ako editor ne klikne "Re-run SEO Review".
3. **Headless-ready, ali ne headless-only** — Plugin radi identično na klasičnom i headless sajtu. Headless je samo dodatni izlazni sloj.
4. **Failure-resilient** — AI padne → graceful degradation. Editor nikad ne ostaje zaglavljen.
5. **Provider-agnostic** — WP AI Client, OpenAI ili Anthropic direktno. Yoast ili Rank Math. Sve adapter pattern.

---

## 2. Strategija faza

Filozofija: **svaka faza ostavlja plugin funkcionalnim, sljedeća se nadograđuje bez refaktora.**

| Faza | Opis | Trajanje | Šta plugin radi nakon nje |
|------|------|----------|---------------------------|
| **Faza 0 — Temelji** | Infrastruktura, adapteri, AI sloj | 3-5 dana | Plugin se instalira, settings rade, AI poziv prolazi (test), ali nema feature-a |
| **Faza 1 — MVP Core** | Publish flow, modal, AEO, regen | 2-3 nedjelje | Editor objavljuje članak → AI review modal → confirm → meta polja popunjena. Production-ready. |
| **Faza 1.5 — Intelligence** | GSC integracija, weekly scan, inbox | 1-2 nedjelje | Plugin proaktivno predlaže popravke za stare članke na osnovu GSC podataka |
| **Faza 2 — Scale** | Bulk, multilingual, audit log, tracking | TBD | Enterprise-spreman za 8000+ članaka, više jezika, compliance |

**Kritično:** Faza 0 je nevidljiva ali nosi sve ostalo. Ne preskakati.

---

## 3. Tehnički stack

### Backend (PHP)
- **PHP 8.1+** (matchuje WP 7.0 minimum)
- **Composer** za autoloading + 3rd party (Guzzle za HTTP, ako WP HTTP API nije dovoljan)
- **PSR-4** namespace: `SeoAiAssistant\`
- **WordPress 6.5+** minimum, WP 7.0 detection za AI Client

### Frontend (JS)
- **React 18** (kompatibilno sa WP Gutenberg)
- **@wordpress/scripts** za build (webpack pod haubom)
- **@wordpress/components** za UI (Modal, Button, Card) — konzistentnost sa Gutenberg-om
- **@wordpress/data** za state management (Redux pod haubom)
- **@wordpress/abilities** (WP 7.0+) za pozive ka backendu
- **SCSS** za stilove, scoped pod `.seo-ai-*` prefix

### AI providers
- **Primary:** `WordPress\AI_Client\AI_Client` (WP 7.0+)
- **Fallback 1:** OpenAI API direktno (`gpt-4o-mini` za jeftino, `gpt-4o` za kvalitet)
- **Fallback 2:** Anthropic API direktno (`claude-sonnet-4-7` za kvalitet)
- Settings: editor bira default model + cijenovni tier

### Integracije
- **Yoast SEO** (primarni SEO adapter)
- **Rank Math** (sekundarni SEO adapter)
- **Google Search Console API** (Faza 1.5)
- **Action Scheduler** (Faza 2, za bulk queue)

---

## 4. Struktura plugina (fajlovi)

```
seo-ai-assistant/
├── seo-ai-assistant.php          # Plugin bootstrap, header, autoload
├── composer.json                  # PHP deps
├── package.json                   # JS deps
├── webpack.config.js              # @wordpress/scripts override ako treba
├── readme.txt                     # WP.org standard
├── README.md                      # Developer dokumentacija
├── uninstall.php                  # Cleanup pri brisanju
│
├── includes/                      # PHP source
│   ├── Plugin.php                 # Glavni singleton, init hooks
│   ├── Activator.php              # Aktivacija (kreira tabele, default options)
│   ├── Deactivator.php            # Deaktivacija
│   │
│   ├── AI/                        # AI service sloj
│   │   ├── AIClientInterface.php
│   │   ├── WPAIClientAdapter.php  # WP 7.0 AI Client
│   │   ├── OpenAIAdapter.php
│   │   ├── AnthropicAdapter.php
│   │   ├── AIServiceFactory.php   # Bira adapter na osnovu okruženja
│   │   └── AIResponse.php         # DTO za AI odgovor
│   │
│   ├── Abilities/                 # WP Abilities API registracija
│   │   ├── AbilityRegistrar.php   # Glavni registry hook
│   │   ├── AnalyzeContentAbility.php
│   │   ├── GenerateMetaAbility.php
│   │   ├── GenerateTagsAbility.php
│   │   └── GenerateAEOAbility.php
│   │
│   ├── SEO/                       # SEO adapter pattern
│   │   ├── SEOAdapterInterface.php
│   │   ├── YoastAdapter.php
│   │   ├── RankMathAdapter.php
│   │   ├── SEOAdapterFactory.php  # Detect Yoast vs Rank Math
│   │   └── NullAdapter.php        # Ako nijedan nije aktivan
│   │
│   ├── AEO/                       # AEO output sloj
│   │   ├── AEOFields.php          # Meta polja registracija (show_in_rest)
│   │   ├── JsonLdRenderer.php     # FAQ schema, entity schema u <head>
│   │   └── AEODataFormatter.php   # REST API output formatter
│   │
│   ├── Prompts/                   # Prompt versioning
│   │   ├── PromptRegistry.php     # Loader sa fallback verzija
│   │   ├── PromptInterface.php
│   │   ├── AnalyzeContentPrompt.php
│   │   ├── GenerateMetaPrompt.php
│   │   ├── GenerateTagsPrompt.php
│   │   ├── GenerateAEOPrompt.php
│   │   └── versions/              # Sirovi prompt fajlovi po verzijama
│   │       ├── analyze_content_v1.txt
│   │       ├── generate_meta_v1.txt
│   │       └── ...
│   │
│   ├── Resilience/                # Failure handling
│   │   ├── RetryHandler.php       # Exponential backoff
│   │   ├── RateLimiter.php        # Per-user limits
│   │   └── FallbackResponder.php  # Šta vratiti kad AI padne
│   │
│   ├── REST/                      # REST API endpoints
│   │   ├── RestController.php     # Bootstrap routes
│   │   ├── GenerateController.php # POST /generate
│   │   ├── ConfirmController.php  # POST /confirm (set _seo_ai_confirmed)
│   │   └── WebhookController.php  # Headless webhook trigger
│   │
│   ├── Admin/                     # Admin UI (PHP dio)
│   │   ├── AdminMenu.php          # Settings stranica
│   │   ├── SettingsPage.php
│   │   ├── EditorAssets.php       # Enqueue React app
│   │   └── PostMetaRegistrar.php  # register_post_meta sa show_in_rest
│   │
│   └── Support/                   # Helper klase
│       ├── Logger.php
│       ├── ContentExtractor.php   # Iz Gutenberg blokova → plain text
│       └── PostTypeRegistry.php   # Koje post types su uključene
│
├── src/                           # React/JS source
│   ├── index.js                   # Entry — registruje sidebar + modal
│   ├── editor-integration.js      # Hook na Publish action
│   │
│   ├── components/
│   │   ├── SidebarPanel/          # Gutenberg sidebar — status indikator
│   │   │   ├── index.jsx
│   │   │   └── styles.scss
│   │   ├── ReviewModal/           # Glavni 2-step modal
│   │   │   ├── index.jsx
│   │   │   ├── ModalHeader.jsx
│   │   │   ├── StepIndicator.jsx
│   │   │   └── styles.scss
│   │   ├── Step1ContentReview/    # Korak 1 — greške i auto-fix
│   │   │   ├── index.jsx
│   │   │   ├── IssueCard.jsx
│   │   │   ├── AutoFixButton.jsx
│   │   │   └── styles.scss
│   │   ├── Step2SeoGeneration/    # Korak 2 — SEO + AEO + tagovi
│   │   │   ├── index.jsx
│   │   │   ├── MetaTitleField.jsx
│   │   │   ├── MetaDescField.jsx
│   │   │   ├── TagsField.jsx
│   │   │   ├── AEOFields.jsx
│   │   │   └── styles.scss
│   │   ├── DiffView/              # Stari vs novi prijedlog
│   │   │   ├── index.jsx
│   │   │   └── styles.scss
│   │   ├── RegenerateButton/
│   │   │   └── index.jsx
│   │   └── ErrorBoundary/
│   │       └── index.jsx
│   │
│   ├── hooks/
│   │   ├── useAIGenerate.js       # React hook za AI poziv
│   │   ├── usePostContent.js      # Čita Gutenberg content
│   │   ├── useFirstPublish.js     # Detektuje da li je prva objava
│   │   └── useAbility.js          # Wrapper za executeAbility()
│   │
│   ├── services/
│   │   ├── api.js                 # REST API klijent
│   │   ├── abilities.js           # @wordpress/abilities wrapper sa fallback
│   │   └── contentParser.js       # Block content → AI input
│   │
│   ├── store/
│   │   ├── index.js               # Redux store registracija
│   │   ├── reducer.js
│   │   ├── actions.js
│   │   └── selectors.js
│   │
│   └── styles/
│       ├── _variables.scss
│       ├── _mixins.scss
│       └── main.scss
│
├── assets/                        # Built JS/CSS (output webpack-a)
│   └── (generated)
│
└── tests/                         # PHPUnit + Jest
    ├── php/
    └── js/
```

---

## 5. Faza 0 — Temelji (3-5 dana)

**Cilj:** Plugin se instalira, settings rade, AI poziv prolazi sa test promptom. Nema vidljivih feature-a, ali sva infrastruktura postoji.

### 5.1 Plugin bootstrap

- `seo-ai-assistant.php` sa standardnim WP plugin header-om
- Composer autoload za `SeoAiAssistant\` namespace
- `Activator` kreira default options, registruje custom capability `manage_seo_ai`
- `Deactivator` čisti scheduled events (za Fazu 1.5 cron)
- `uninstall.php` briše sve plugin options i meta polja ako korisnik to izabere u settings

### 5.2 Settings stranica

Lokacija: `Settings > SEO AI Assistant`

Polja:
- **AI Provider Strategy:** dropdown (Auto-detect / WP AI Client only / OpenAI / Anthropic)
- **API Key (OpenAI)** — masked input, čuva se kao encrypted option
- **API Key (Anthropic)** — isto
- **Default model:** dropdown sa cost tier indikatorom
- **Brand voice instructions:** textarea (50-500 znakova) — ubacuje se u svaki prompt
- **Aktivni post types:** checkbox grupa (samo `post`, `kategorije`, `oznake` + custom post types, kako si rekao)
- **SEO plugin:** auto-detect Yoast/Rank Math (read-only display)
- **Rate limit:** broj regen po članku po useru po satu (default 10)
- **Failure behavior:** "Skip i nastavi" / "Blokiraj objavu" radio
- **Headless mode:** checkbox + URL polje za webhook revalidacije

### 5.3 SEO Adapter (kritično za temelje)

`SEOAdapterInterface` definiše:
```php
interface SEOAdapterInterface {
    public function isActive(): bool;
    public function getMetaTitle(int $postId): string;
    public function setMetaTitle(int $postId, string $title): void;
    public function getMetaDescription(int $postId): string;
    public function setMetaDescription(int $postId, string $desc): void;
    public function getFocusKeyword(int $postId): string;
    public function setFocusKeyword(int $postId, string $kw): void;
    public function registerRestFields(): void;  // show_in_rest setup
    public function getCanonicalUrl(int $postId): ?string;
}
```

`YoastAdapter` mapira na `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_focuskw`.

`RankMathAdapter` mapira na `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`.

`SEOAdapterFactory::create()` provjerava `is_plugin_active()` i vraća tačan adapter. `NullAdapter` je fallback (loguje warning, ne briše ništa).

**Važno:** Adapteri registruju polja kao `show_in_rest => true` kroz `PostMetaRegistrar` da headless frontend može čitati Yoast/Rank Math meta polja. Yoast po defaultu ovo ne radi.

### 5.4 AI Service sloj

`AIClientInterface`:
```php
interface AIClientInterface {
    public function generate(
        string $prompt,
        array $context = [],
        array $options = []  // model, temperature, max_tokens
    ): AIResponse;

    public function isAvailable(): bool;
    public function getProviderName(): string;
}
```

`AIServiceFactory::create()` logika:
1. Ako `class_exists('WordPress\\AI_Client\\AI_Client')` i settings dozvoljava → `WPAIClientAdapter`
2. Inače ako ima OpenAI key → `OpenAIAdapter`
3. Inače ako ima Anthropic key → `AnthropicAdapter`
4. Inače → throw exception, prikaži admin notice

`AIResponse` DTO:
```php
class AIResponse {
    public string $content;
    public string $model;
    public int $inputTokens;
    public int $outputTokens;
    public array $rawResponse;
    public bool $success;
    public ?string $errorMessage;
}
```

### 5.5 Abilities API registracija

Sve AI funkcije izložene kroz Abilities API. Ovo je **glavni interfejs** za sve buduće faze.

Registrovane abilities:
- `seo-ai-assistant/analyze-content` — analiza članka, vraća issues array
- `seo-ai-assistant/generate-meta` — Yoast meta title + description
- `seo-ai-assistant/generate-tags` — niz tagova (3-8 komada)
- `seo-ai-assistant/generate-aeo` — TL;DR + FAQ + glavno pitanje + entiteti

Sve abilities imaju:
- `permission_callback` koji traži `edit_posts` capability + provjera vlasništva članka
- JSON Schema za input (postId, regenField, contextOverride)
- JSON Schema za output (strukturirani format)
- `execute_callback` koji poziva odgovarajući Prompt + AI Service

**Headless aspekt:** Abilities **nisu** javne preko REST-a. `permission_callback` zahtjeva login + edit_posts. Headless frontend čita samo finalne meta podatke (Yoast `yoast_head_json` + naš `seo_ai_aeo` polje), ne AI poziv.

### 5.6 Prompt versioning

Promptovi su tekstualni fajlovi u `includes/Prompts/versions/`. Naziv format: `{ability}_{version}.txt`.

`PromptRegistry` čita aktivnu verziju iz options table (`seo_ai_prompt_versions` option). Editor može pinom-vati verziju ako želi rollback. Default je `latest`.

Prompt sintaksa koristi placeholdere: `{{post_content}}`, `{{post_title}}`, `{{brand_voice}}`, `{{language}}`, `{{gsc_context}}` (prazan u Fazi 1, popunjen u Fazi 1.5).

Primjer (`analyze_content_v1.txt`):
```
Ti si SEO ekspert. Analiziraj sljedeći članak.

Jezik članka: {{language}}
Brand voice: {{brand_voice}}

NASLOV: {{post_title}}

SADRŽAJ:
{{post_content}}

{{gsc_context}}

Vrati JSON sa sljedećom strukturom:
{
  "issues": [
    {
      "type": "missing_h2" | "thin_content" | "no_intro" | "keyword_stuffing" | "broken_structure",
      "severity": "high" | "medium" | "low",
      "message": "...",
      "suggestion": "...",
      "autofix": {
        "available": true | false,
        "action": "insert_block" | "modify_text" | "reorder",
        "data": {...}
      }
    }
  ],
  "overall_score": 0-100,
  "summary": "..."
}
```

### 5.7 Failure resilience

`RetryHandler`:
- 3 pokušaja, exponential backoff (1s, 2s, 4s)
- Razlikuje retry-able greške (timeout, 5xx, rate limit) od fatal (4xx auth, bad request)
- Loguje sve neuspjehe u WP error log + plugin log tabelu

`RateLimiter` koristi transients:
- Key: `seo_ai_rate_{user_id}_{post_id}`
- Increment per regen poziv
- Reset svakih 60 min
- Hard limit iz settings (default 10)

`FallbackResponder`:
- Ako AI provider potpuno padne, vraća strukturirani fallback objekat sa porukom u modal-u
- Modal pokazuje: "AI servis trenutno nije dostupan. [Preskoči ovaj korak] [Pokušaj ponovo]"
- Ako editor preskoči, `_seo_ai_confirmed` flag se postavi sa `skipped: true` razlogom (za audit u Fazi 2)


### 5.8 Deliverable za Fazu 0

Po završetku ove faze postoji:
- WP-CLI komanda `wp seo-ai test-connection` koja pokreće test AI poziv i vraća uspjeh/neuspjeh
- Settings stranica radi, sva polja perzistiraju
- `SEOAdapterFactory::create()->getMetaTitle($id)` vraća tačnu vrijednost za Yoast i Rank Math (proveriti integration test-om)
- Abilities su registrovane (vidljive na `wp-admin > Tools > Abilities` ako WP 7.0)
- Nema frontend dijela još

---

## 6. Faza 1 — MVP Core (2-3 nedjelje)

**Cilj:** Production-ready publish flow. Editor objavljuje članak → modal → AI generiše SEO/AEO → editor potvrdi → meta polja popunjena. Plugin dnevno koristan.

### 6.1 Detekcija "prva objava"

Logika:
1. Editor klikne `Publish` ili `Save` (drafts ne triggeruju)
2. JS intercepter (`editor-integration.js`) pita backend: "Da li ovaj post ima `_seo_ai_confirmed` meta?"
3. Ako **nema** → otvori modal, blokiraj `core/editor` `savePost` action dok se ne potvrdi
4. Ako **ima** → pusti normalan save flow
5. Sidebar panel uvijek pokazuje status: "Nije pregledano" / "Potvrđeno {datum}" / dugme "Re-run SEO Review"

**Edge case:** Ako editor "Save as Draft" → publish kasnije, `_seo_ai_confirmed` se ne postavlja na draft save. Postavlja se tek kad publish flow završi sa Confirm.

**Edge case:** Scheduled posts — modal se otvara prilikom postavljanja schedule-a, ne prilikom auto-publish.

### 6.2 React app entry

`src/index.js` registruje:
- Gutenberg sidebar panel (`registerPlugin` API)
- Filter na `core/editor` `savePost` action koji otvara modal
- Redux store za plugin state
- `ErrorBoundary` wrapper oko cijele aplikacije

### 6.3 Sidebar panel

Sadržaj:
- Status indikator (boja + tekst)
- Postojeće Yoast/RankMath meta polja preview (read-only)
- Postojeće AEO polja preview
- Dugme "Re-run SEO Review" (otvara modal manuelno)
- Mali "info" tooltip sa zadnjim datumom potvrde

### 6.4 ReviewModal — 2-step flow

**Otvaranje:**
- Trigger: pokušaj `publish` na članku bez `_seo_ai_confirmed`
- Pre-load: paralelno pozove sve 4 abilities (`analyze`, `meta`, `tags`, `aeo`)
- Loading state pokazuje 4 skeleton kartice
- Svaki AI poziv završava nezavisno (Promise.all sa allSettled)

**Korak 1 — Content Review:**

UI: Lista kartica, svaka kartica = jedan issue.

Kartica:
```
[severity badge: HIGH] Članak nema H2 podnaslove

Tekst: Pronašao sam da članak sadrži samo H1 i odmah paragrafe.
Google preferira članke sa H2-H3 hierarchijom.

Sugestija: Dodaj H2 prije sekcija o "Kako koristiti X" i "Najbolje prakse".

[Primijeni automatski] [Preskoči ovaj]  [Regeneriši]
```

Auto-fix akcije (preko `wp.data.dispatch('core/block-editor')`):
- `insert_block` — ubaci heading blok na predloženoj poziciji
- `modify_text` — zamijeni tekst u postojećem bloku
- `reorder` — preuredi redoslijed blokova

Editor dugmad na dnu Koraka 1:
- `[Preskoči Korak 1, idi na SEO]` — ako editor ne želi rješavati issue-e
- `[Nastavi na Korak 2]` — primarno dugme

**Re-analyze logic:** Ako je editor primijenio bilo koji auto-fix, prilikom prelaska na Korak 2, plugin **ponovo poziva** `analyze-content` ability sa svježim sadržajem članka. Onda se na Korak 2 šalje **najnoviji** sadržaj za `generate-meta`, `generate-tags`, `generate-aeo`. Bez ovoga rezultat ne bi reflektovao promjene iz Koraka 1.

**Korak 2 — SEO Generation:**

UI: 4 sekcije, svaka sa current value, AI suggestion, Diff view dugme, Regenerate dugme.

Sekcije:
1. **Meta naslov** (Yoast)
   - Trenutno: prikaz postojećeg ili "(prazno)"
   - AI prijedlog: editabilni textbox (predpopunjen)
   - Character counter (max 60)
   - `[Prikaži diff]` `[Regeneriši]`

2. **Meta opis** (Yoast)
   - Slično, max 160 znakova counter

3. **Tagovi** (post tags)
   - AI prijedlozi: niz chip-ova, svaki sa X dugmetom za uklanjanje
   - Editor može tipkati svoje tagove (autocomplete iz postojećih)
   - `[Regeneriši]` dugme regeneriše cijeli set

4. **AEO polja**
   - 4 sub-sekcije: TL;DR, FAQ, Glavno pitanje, Entiteti
   - **TL;DR:** textarea 40-60 riječi, character counter
   - **FAQ:** lista Q&A parova (add/remove dugmad), min 2 max 8
   - **Glavno pitanje:** text input
   - **Entiteti:** chip-ovi (read-only ili editabilno?)
   - `[Regeneriši]` dugme regeneriše sva 4

Dugmad na dnu Koraka 2:
- `[Nazad na Korak 1]` (sekundarno)
- `[Potvrdi SEO]` (primarno) — disabled dok loading

**Confirm flow:**
1. Klik `Potvrdi SEO` → POST `/seo-ai-assistant/v1/confirm` sa svim vrijednostima
2. Backend validira (capability, sanity check vrijednosti)
3. Backend snima:
   - Yoast/Rank Math polja kroz adapter
   - Tags kroz `wp_set_post_terms()`
   - AEO polja kroz `update_post_meta()`
   - `_seo_ai_confirmed = ['date' => now, 'user_id' => current, 'version' => '1.0']`
4. Modal se zatvara
5. Originalni `savePost` action se nastavlja → članak se publishuje
6. Ako headless webhook je aktiviran → trigger se POST na frontend URL

### 6.5 DiffView komponenta

Side-by-side prikaz: lijevo "Trenutno", desno "AI prijedlog". Highlight razlika (zelenom dodato, crvenom uklonjeno).

Za meta polja — character-level diff.
Za tagove — set diff (koji su novi, koji su uklonjeni).
Za AEO TL;DR — character-level.
Za FAQ — itemized (svaki Q&A par odvojeno).

Bazirano na `diff` npm paketu (lagan, ~10kb).

### 6.6 RegenerateButton komponenta

Klik:
1. Pokrene loading state SAMO za to polje (ne cijeli modal)
2. Pošalje `executeAbility(ability, { postId, regenField: 'meta_title' })` (ili kako se već zove)
3. Provjerava rate limit (response može vratiti 429)
4. Ako rate limit: prikaže poruku "Limit dostignut, pokušaj za X minuta"
5. Ako uspjeh: stari prijedlog ide u `previousValue`, novi u `currentSuggestion`, automatski otvara DiffView

### 6.7 AEO Output (klasični + headless)

**Klasični WP:**
`JsonLdRenderer` hookuje na `wp_head`:
- Ako post ima `_seo_ai_aeo_faq` meta → renderuje `<script type="application/ld+json">` sa FAQPage schema
- Ako post ima `_seo_ai_aeo_entities` → renderuje `Article` schema sa `about` propertyjem
- Ne diramo Yoast schema, samo dodajemo dodatne

**Headless:**
`PostMetaRegistrar` registruje sva AEO polja sa `show_in_rest => true`:
- `seo_ai_aeo_tldr`
- `seo_ai_aeo_faq` (array tip)
- `seo_ai_aeo_main_question`
- `seo_ai_aeo_entities` (array tip)

Plus, dodatni REST endpoint `/seo-ai-assistant/v1/aeo/{post_id}` koji vraća sve AEO podatke + **predgenerisani JSON-LD blob** koji Next.js može direktno ubaciti u `<head>`.

### 6.8 Headless webhook (opciono)

Settings:
- Checkbox "Enable headless revalidation"
- URL polje (e.g. `https://frontend.com/api/revalidate`)
- Secret token za HMAC potpisivanje

Trigger: nakon `Confirm SEO` action.
Payload: `{ post_id, slug, post_type, action: 'seo_confirmed', signature: 'hmac...' }`.

### 6.9 Deliverable za Fazu 1

- Editor može objaviti novi članak, modal se otvori
- Sva 4 AI poziva rade i daju strukturirane odgovore
- Auto-fix dugmad u Koraku 1 mijenjaju Gutenberg blokove
- Regen dugmad rade sa diff view
- Confirm snima sve podatke
- Yoast i Rank Math pišu se isto kroz adapter
- AEO JSON-LD se renderuje u `<head>` klasičnog sajta
- AEO podaci dostupni preko REST API za headless
- "Prva objava only" logika funkcioniše
- "Re-run SEO Review" dugme manuelno otvara modal


---

## 7. Faza 1.5 — Intelligence Layer (1-2 nedjelje)

**Cilj:** Plugin proaktivno traži probleme na već objavljenim člancima na osnovu stvarnih Google Search Console podataka. Editor dobija "inbox" prijedloga.

### 7.1 Google Search Console OAuth

- Settings stranica dobija novu sekciju "Google Search Console"
- Dugme "Connect to GSC" pokreće OAuth flow (Google Cloud Console projekt klijenta)
- Token se čuva encrypted u `seo_ai_gsc_token` option
- Refresh token automatski refresh-uje svakih 50 min
- Settings: izbor verifikovane property (dropdown popunjen iz API)

Tehnički:
- Koristimo `google/apiclient` Composer paket (lightweight wrapper)
- OAuth redirect URL: `/wp-admin/admin.php?page=seo-ai-assistant&gsc_callback=1`
- Scope: `https://www.googleapis.com/auth/webmasters.readonly`

### 7.2 GSC Data Fetcher

Servis koji za dati `post_id` (i njegov canonical URL) vraća:
- Top 10 queries (impressions, clicks, CTR, position) za zadnjih 28 dana
- Position trend (avg position zadnja 7 dana vs 28 dana prije)
- Total impressions trend

Caching:
- Transient `seo_ai_gsc_{post_id}` traje 24h
- Manual refresh dugme u sidebar panel-u

### 7.3 AI prompt-ovi dobijaju GSC context

Postojeći promptovi iz Faze 1 imaju `{{gsc_context}}` placeholder koji je bio prazan. Sad se popunjava:

```
GSC PODACI ZA OVAJ ČLANAK (zadnjih 28 dana):
- Top queries:
  1. "kako napraviti X" — 1240 impressions, CTR 1.8%, avg position 8.2
  2. "X tutorial" — 890 impressions, CTR 3.1%, avg position 5.4
  3. ...
- Trend: pozicija pala sa 5.1 (prije 28 dana) na 8.2 (sad)
- Loš CTR (<2%) za top query — meta description ne reaguje na search intent

Iskoristi ove podatke da preporučiš popravke. Posebno optimizuj za queries sa visokim impressions a niskim CTR.
```

**Bitno:** Ovo radi automatski. Faza 1 promptovi se već koriste — sad su samo "pametniji". Bez refaktora.

### 7.4 Weekly Scan (cron)

WP-Cron event `seo_ai_weekly_scan`, pokreće se nedjeljom u 03:00 (configurable).

Logika:
1. Query svih objavljenih članaka iz aktivnih post types (limit 100 po runu da ne tresne resurse)
2. Za svaki članak: povuci GSC podatke, izračunaj "health score"
3. Health score formula:
   - Position trend (loš ako pao >2 mjesta)
   - CTR vs benchmark (loš ako <50% benchmark-a za tu poziciju)
   - Coverage issues (loš ako Google izvještava bilo šta)
4. Ako score < threshold → AI generiše prijedlog popravke
5. Sve prijedloge upišemo u custom tabelu `wp_seo_ai_suggestions`

Tabela schema:
```sql
CREATE TABLE wp_seo_ai_suggestions (
  id BIGINT AUTO_INCREMENT,
  post_id BIGINT,
  type VARCHAR(50),  -- 'meta_title' | 'meta_desc' | 'aeo_refresh' | etc
  current_value TEXT,
  suggested_value TEXT,
  reason TEXT,
  gsc_snapshot JSON,
  status ENUM('pending', 'accepted', 'rejected', 'expired'),
  created_at DATETIME,
  reviewed_at DATETIME NULL,
  reviewed_by BIGINT NULL,
  PRIMARY KEY (id),
  INDEX (status, created_at),
  INDEX (post_id)
);
```

### 7.5 SEO Suggestions Inbox

Novi admin screen: `Posts > SEO Suggestions Inbox`

UI:
- List view, jedan red = jedan prijedlog
- Kolone: članak (link), tip prijedloga, razlog, "Prikaži diff", action dugmad
- Filteri: status (pending default), tip, post type, datum
- **Nema bulk akcija** — svaki prijedlog se prihvata/odbija pojedinačno (vidi sekciju 18.2)

Klik na prijedlog → expandable detail view:
- Diff view (isti DiffView komponent iz Faze 1)
- GSC kontekst (zašto AI predlaže promjenu)
- `[Prihvati]` `[Prihvati uz izmjene]` `[Odbij]` `[Snooze 7 dana]`

Klik `Prihvati` → poziva isti `confirm` endpoint iz Faze 1.

### 7.6 Email digest

Cron event `seo_ai_weekly_digest`, ponedjeljkom u 08:00.

Šalje email svim korisnicima sa capability `manage_seo_ai`:
- Subject: "SEO AI: {N} novih prijedloga za pregled"
- Body: top 5 prijedloga, link na inbox, kratak summary trenda

### 7.7 Performance tracking

Kad editor `Prihvati` prijedlog:
1. Snimi snapshot trenutnog GSC stanja u `seo_ai_suggestions.gsc_snapshot`
2. Nakon 28 dana, cron task uporedi tadašnje vs trenutno stanje
3. Loguje rezultat u `wp_seo_ai_outcomes` tabelu
4. Admin dashboard widget pokazuje: "Prošli mjesec ste prihvatili 23 prijedloga, prosječno poboljšanje CTR: +0.8pp"

### 7.8 Deliverable za Fazu 1.5

- GSC connection radi end-to-end
- Members vide GSC podatke u sidebar panel-u tokom Faze 1 modal-a
- Weekly cron pravi prijedloge
- Inbox admin screen funkcioniše
- Email digest se šalje
- Performance tracking počinje sakupljati podatke


---

## 8. Faza 2 — Scale & Enterprise (TBD)

**Cilj:** Plugin spreman za enterprise scale i compliance.

Funkcionalnosti za Fazu 2:
- **Multilingual support** (WPML + Polylang detection, prevod-aware Yoast polja)
- **Audit log** (kompletan history ko/kada/šta je AI predložio i šta editor odlučio)
- **Token & cost tracking** sa admin dashboard widget-om
- **Bypass capability** za senior urednike (`bypass_seo_ai_review`)
- **Bulk operacije**:
  - Admin UI: max 50 članaka po batch-u, mandatory preview prije Apply
  - WP-CLI komanda za 100+ članaka
  - Action Scheduler queue
  - Rollback dugme po batch-u
- **Advanced rate limiting** sa per-role limitima
- **Prompt A/B testing** infrastruktura

---

## 9. Tehnički detalji / dizajn odluke

### 9.1 Zašto Abilities API a ne klasični REST?

- WP 7.0 native za AI workflows
- Automatski self-documenting (`/wp-abilities/v1/abilities`)
- JS klijent (`@wordpress/abilities`) sa fallback-om radi out-of-the-box
- Permissions, validacija ulaza/izlaza centralizovani
- MCP-spreman za budućnost (Claude/ChatGPT mogu direktno koristiti plugin)
- Konzistentno sa pravcem u kojem WP ide

Fallback za WP 6.5-6.8: plugin detektuje da Abilities nema, registruje obične REST endpointe sa istim shape-om. Frontend `useAbility` hook apstrahuje razliku.

### 9.2 Zašto adapter pattern za AI?

- WP 7.0 izlazi 20. maja 2026 — plugin mora raditi i prije i poslije
- Klijenti mogu imati različite cost preferences (OpenAI gpt-4o-mini vs Anthropic claude-sonnet)
- Bolje za testing — možemo mock-ovati `AIClientInterface` u PHPUnit
- Bolje za budućnost — novi providers se dodaju sa 1 fajlom

### 9.3 Zašto AEO polja kao posebna meta (a ne kroz Yoast)?

Yoast ne podržava strukturirana AEO polja (TL;DR, FAQ schema). Custom polja:
- Daju nam slobodu strukture
- Lakša REST API ekspozicija
- Lakše extend-ovati (dodavati nova polja)
- Yoast može i dalje generisati svoj `<head>`, mi samo dodajemo JSON-LD pored

### 9.4 Block content extraction strategija

Gutenberg sadržaj je niz blokova sa attributes. AI ne treba HTML, treba semantičku reprezentaciju:

```
{ContentExtractor::extract($post)} vraća:
"# Naslov članka

## Prva sekcija
Paragraf tekst...

## Druga sekcija
- Lista item 1
- Lista item 2

[image: alt='opis slike']
[embed: youtube video]

> Citat
"
```

Ovo AI lakše parsira nego raw blocks JSON. Klasični editor → fallback na `get_the_content()` + `wp_strip_all_tags()`.

### 9.5 Sigurnost

- **Nonce verification** za sve REST/Ability pozive
- **Capability check** (`edit_posts` minimum, `manage_seo_ai` za settings)
- **API keys encrypted** u DB (koristimo `wp_options` sa secret iz `wp-config.php` kao key)
- **Output sanitization** — AI output uvijek prolazi kroz `wp_kses_post`, `sanitize_text_field` ili `sanitize_textarea_field`
- **Rate limiting** štiti od abuse-a (i troška)
- **HMAC potpis** za headless webhook
- **No content leakage** — settings checkbox "exclude private posts from AI" (default ON)

### 9.6 Performance

- AI pozivi su async (`Promise.all`) u frontendu, paralelno
- Backend caching na transient (24h za GSC, 1h za AI rezultate ako se modal ponovo otvori)
- Lazy load React bundle samo na editor screen-ovima (`wp_enqueue_script` conditional)
- AEO JSON-LD se generiše jednom pri save, cache-uje u meta (ne svaki page load)

### 9.7 Internacionalizacija (i18n)

Sav UI text prolazi kroz `__()` (PHP) i `__('...', 'seo-ai-assistant')` (JS). Plan: BA/HR/SR i EN strings, ostali po potrebi. Prompts su zasebna stvar — AI radi na bilo kojem jeziku, plugin samo prosljeđuje content.

---

## 10. Acceptance kriterijumi po fazama

### Faza 0
- [ ] `wp seo-ai test-connection` vraća success za bar jedan AI provider
- [ ] Settings stranica čuva sve opcije
- [ ] Yoast adapter čita i piše meta (ako Yoast aktivan)
- [ ] Rank Math adapter čita i piše meta (ako Rank Math aktivan)
- [ ] `register_post_meta` poziva sa `show_in_rest => true` za sva relevantna polja
- [ ] Abilities su vidljive u Abilities registry
- [ ] PHPUnit test suite prolazi za AI sloj sa mock provider-om

### Faza 1
- [ ] Editor objavi novi članak → modal se otvori
- [ ] Modal se NE otvara kod druge save akcije
- [ ] "Re-run SEO Review" dugme manuelno otvara modal
- [ ] Sva 4 AI poziva završavaju paralelno za <30s
- [ ] Auto-fix dugmad mijenjaju Gutenberg blokove
- [ ] Re-analyze logic radi nakon promjena u Koraku 1
- [ ] Regenerate radi za svako pojedinačno polje
- [ ] Diff view se otvara nakon regen
- [ ] Confirm snima sve podatke u jedan REST poziv (atomic)
- [ ] AEO JSON-LD se renderuje u `<head>` klasičnog sajta
- [ ] AEO polja su dostupna preko REST API
- [ ] Headless webhook se okida ako je aktiviran
- [ ] Rate limiting blokira excessive regen
- [ ] Failure resilience radi (test sa lažno padlim AI)

### Faza 1.5
- [ ] GSC OAuth flow završi se uspješno
- [ ] GSC podaci se prikazuju u sidebar panel-u
- [ ] Promptovi u Fazi 1 sad uključuju GSC context kad je dostupan
- [ ] Weekly cron generiše prijedloge
- [ ] Inbox screen prikazuje prijedloge
- [ ] Accept akcija snima podatke kao u Fazi 1 confirm
- [ ] Email digest stiže ponedjeljkom
- [ ] Performance tracking počinje akumulirati podatke

---

## 11. Open questions (za prije kodiranja)

Ovo treba odlučiti prije nego što Claude Code krene:

1. **Plugin slug i namespace** — `seo-ai-assistant` i `SeoAiAssistant`? Ili nešto brand-specific?
2. **Glavni AI model** — `gpt-4o-mini` (jeftin, dobar) ili `claude-sonnet` (skuplji, bolji za content)? Predlažem da settings ima default + per-ability override.
3. **Jezik prompt-ova** — pisati promptove na engleskom (univerzalno) ili na bosanskom/hrvatskom (bolji output za lokalne članke)? Predlažem english promptove sa eksplicitnim "respond in {{language}}" instrukcijom.
4. **Custom post types** — Specifikuj tačno koje će biti uključeni. Ti si rekao "Članke, Kategorije i Oznake + Custom post type članci". Trebamo listu CPT slugs.
5. **Brand voice** — Imaš li već postojeću brand voice guide koju ćemo koristiti? Ako ne, settings polje ostaje prazno i admin ga popuni kasnije.
6. **Gutenberg vs Classic** — Da li klijent koristi i Classic editor negdje? Ako da, fallback za Classic je dodatni rad.
7. **Hosting context** — Šta je hosting plana? Cron jobs (Faza 1.5) zahtijevaju real cron, ne wp-cron, na enterprise sajtovima.

---

## 12. Razvojni proces sa Claude Code

Preporuka kako gurati ovo kroz Claude Code:

**Sesija 1:** Faza 0, fajlovi 5.1–5.3 (bootstrap + settings + SEO adapter)
**Sesija 2:** Faza 0, fajlovi 5.4–5.7 (AI service + abilities + prompts + resilience)
**Sesija 3:** Faza 1, fajlovi 6.1–6.3 (publish detection + React entry + sidebar)
**Sesija 4:** Faza 1, fajl 6.4 (ReviewModal — najveći komad)
**Sesija 5:** Faza 1, fajlovi 6.5–6.9 (diff, regen, AEO output, webhook)
**Sesija 6:** Testing + bug fixes za Fazu 1
**Sesija 7+:** Faza 1.5 sekcije

Svaka sesija da uključi:
1. Read-only acceptance kriterijume iz ovog dokumenta
2. Postojeću strukturu fajlova (ako je nešto već urađeno)
3. Konkretan scope sesije
4. Tests-first kad god je moguće

---

## 13. Risks i mitigacije

| Risk | Vjerovatnoća | Uticaj | Mitigacija |
|------|-------------|--------|------------|
| WP 7.0 AI Client API se mijenja prije release-a | Srednja | Visok | Hibridni adapter — ako se API mijenja, mijenjamo samo `WPAIClientAdapter` |
| Yoast updateuje meta keys | Niska | Srednji | Adapter pattern izoluje |
| AI generiše loš sadržaj | Srednja | Visok | Mandatory human review (osnovni princip plugin-a) |
| GSC API rate limits | Srednja | Srednji | Caching + bulk request strategy |
| OpenAI/Anthropic outage | Niska | Srednji | Multi-provider fallback + graceful degradation |
| Performance issues na velikim člancima | Srednja | Srednji | Content truncation (max ~8000 tokena input) + async pozivi |
| Editor odbije plugin zbog UX friction | Srednja | Visok | First-publish-only, brz modal, dobar copy, "skip" opcije gdje smije |


---

## 14. Donesene odluke (finalne, ne mijenjati bez razgovora)

Ova sekcija sadrži sve odluke iz Open Questions faze. Claude Code mora ovo poštovati.

### 14.1 Identitet plugina
- **Plugin slug:** `ai-seo-assistant`
- **Plugin folder:** `ai-seo-assistant/`
- **Main file:** `ai-seo-assistant.php`
- **PHP namespace:** `AiSeoAssistant\`
- **Text domain:** `ai-seo-assistant`
- **Author:** Alen Melkic
- **Option prefix:** `aisa_` (npr. `aisa_settings`, `aisa_gsc_token`)
- **Meta prefix:** `_aisa_` (npr. `_aisa_confirmed`, `_aisa_aeo_tldr`)
- **REST namespace:** `ai-seo-assistant/v1`
- **JS global:** `window.aiSeoAssistant`
- **CSS prefix:** `aisa-` (npr. `.aisa-modal`, `.aisa-step-1`)
- **Capability:** `manage_aisa` (custom, mapirana na `manage_options` po defaultu)

### 14.2 AI Model strategija

**Default model setting** + **per-ability override**.

Settings UI:
- **Default model:** dropdown (gpt-4o-mini / gpt-4o / claude-sonnet-4-7 / WP AI Client preferences)
- **Override per ability:** accordion sekcija (collapsed po defaultu) sa 4 dropdowna:
  - Content Analysis model: [koristi default | gpt-4o | claude-sonnet-4-7 | ...]
  - Meta Generation model: ...
  - Tags Generation model: ...
  - AEO Generation model: ...

**Preporuka kao default:**
- `claude-sonnet-4-7` za Content Analysis i AEO (kvalitetnije razumijevanje sadržaja)
- `gpt-4o-mini` za Meta Generation i Tags Generation (jeftino i dovoljno za kratke outputs)

To je hibrid koji optimizuje cost vs quality. Settings ima preset dugme "Apply recommended" koje postavi ovo.

### 14.3 Jezik prompts

**Engleski promptovi + eksplicitan respond-in instrukcija sa BOSANSKI ENFORCEMENT.**

Svaki prompt template ima blok:

```
RESPONSE LANGUAGE: {{language}}

IF {{language}} == "bs" (Bosnian), FOLLOW STRICTLY:
- Use Bosnian ijekavica EXCLUSIVELY (mlijeko, vrijeme, dijete, lijep, bijel)
- NEVER use Croatian-specific words:
  AVOID: tjedan, kolovoz, siječanj, veljača, ožujak, travanj, svibanj, lipanj, srpanj, kolovoz, rujan, listopad, studeni, prosinac, tisuća, djelatnik, tvrtka, glazba, povijest, nogomet, ured
  USE: sedmica, august, januar, februar, mart, april, maj, juni, juli, august, septembar, oktobar, novembar, decembar, hiljada, uposlenik, firma/preduzeće, muzika, historija, fudbal, kancelarija
- NEVER use Serbian ekavica words:
  AVOID: mleko, vreme, dete, lep, beo
  USE: mlijeko, vrijeme, dijete, lijep, bijel
- Use Latinic script (NOT Cyrillic)
- Natural Bosnian sentence structure
- Turkish-origin loanwords are acceptable where natural (komšija, džamija, čaršija)

IF {{language}} == "en":
- Standard English, professional tone
- Brand voice from {{brand_voice}} applies

IF {{language}} == "hr" or "sr":
- Use language as standard for that variant
```

**Detekcija jezika:**
1. Plugin čita `get_locale()` (npr. `bs_BA`, `hr`, `sr_RS`)
2. Maptira na `bs`, `hr`, `sr`, `en`
3. Settings stranica ima override "Force language" dropdown (default: auto)
4. Per-post override moguć kroz sidebar panel (dropdown "Jezik AI generisanja")

**Post-generation validation za bosanski:**

`BosnianValidator` klasa skenira AI output za "warning words" liste iznad. Ako pronađe:
- Sidebar prikaže warning ikonicu pored polja
- Tooltip: "AI je možda upotrijebio hrvatsku/srpsku riječ: '{word}'. Predloženi bosanski ekvivalent: '{replacement}'"
- Editor može ignorisati ili kliknuti "Zamijeni"
- Ne blokira confirm — samo upozorenje

### 14.4 Custom Post Types — auto-discovery

Plugin **automatski otkriva** sve CPT-ove na sajtu.

Logika:
1. `get_post_types(['public' => true], 'objects')` na admin_init
2. Filtrira out built-in: `attachment`, `nav_menu_item`, `revision`, `wp_block`
3. Builtin koji ostaju: `post`, `page`
4. Settings stranica prikazuje sve detektovane post types kao checkbox listu
5. Sa labelom: post slug + "(built-in)" ili "(custom)"

**Default activation pravilo:**
- `post` → enabled by default
- `page` → disabled by default (editor odlučuje)
- Svaki custom CPT → **disabled by default** (sigurnost)

**Notice za nove CPT-ove:**

Plugin čuva listu poznatih CPT-ova u option `aisa_known_post_types`. Pri svakom admin load:
1. Trenutni `public` CPT-ovi se uporede sa poznatima
2. Ako ima novih → admin notice "Detektovan novi post type: '{label}' ({slug}). Uključiti u AI SEO Assistant? [Otvori settings]"
3. Notice dismiss-iv (čuva se u `aisa_dismissed_notices`)

**Kategorije i Tagovi:**
Ti si rekao "Članke, Kategorije i Oznake". Kategorije i tagovi su **taxonomije**, ne post types. Plugin ih obrađuje kroz `post_tag` i `category` REST endpointe — generiše tagove ZA članke (Faza 1) i može generisati meta opise ZA taxonomy terms (potencijalno Faza 2).

Za sad u Fazi 1: AI generiše tagove i automatski ih dodjeljuje preko `wp_set_post_terms()`. Kategorije se NE diraju automatski (one su strukturalne odluke editora).

### 14.5 Brand voice

**Opcija dostupna, prazno po defaultu.**

Settings stranica → "Content Strategy" sekcija:
- **Brand voice textarea** (max 1000 znakova)
  - Placeholder: "Primjer: Pišemo informalno, koristimo 'ti', izbjegavamo žargon, držimo se 2-3 rečenice po paragrafu..."
  - Help text: "Ovo se ubacuje u svaki AI prompt. Što specifičnije, to bolje. Ostavi prazno ako nemaš još."
- **Sample content** textarea (max 2000 znakova)
  - "Zalijepi 1-2 reprezentativna paragrafa tvog postojećeg sadržaja. AI će ovo analizirati da imitira stil."
  - Help text: "Opciono. Daje bolji rezultat nego samo opis."
- **Audience description** textarea (max 500 znakova)
  - "Ko su tvoji čitaoci? Stručnjaci, početnici, mješovita publika?"

Sva tri polja su opcionalna. Ako su sva prazna, prompt jednostavno ne dobija `{{brand_voice}}` sekciju.

### 14.6 Editor support — Gutenberg + Classic dual mode

Plugin podržava OBA editora.

**Detekcija po post:**
```php
$is_block_editor = use_block_editor_for_post($post);
```

**Gutenberg flow (postojeći plan):**
- React sidebar panel
- Modal preko `registerPlugin` + `PluginSidebar`
- Auto-fix dugmad rade kroz `wp.data.dispatch('core/block-editor')`
- Full feature set

**Classic editor flow:**
- Meta box ispod editora ("AI SEO Assistant")
- Status indikator + "Re-run SEO Review" dugme
- Modal isti React komponent, ali se okida iz meta box-a
- Modal renderuje **direktno u DOM** (`ReactDOM.createPortal` na `<body>`)
- **Auto-fix u Koraku 1 NIJE dostupan** — zamijenjen sa:
  - "Copy suggestion" dugme (kopira AI suggestion u clipboard)
  - Tekstualne instrukcije ("Dodaj ovaj H2 prije sekcije 'X'")
  - Editor ručno mijenja u TinyMCE
- Korak 2 (SEO/AEO) radi identično
- Confirm flow identičan

**Detekcija u publish flow:**

Gutenberg: hook na `core/editor` `savePost`.
Classic: hook na `submit` event forme `#post`, intercept preko jQuery.

Ovo znači dvije ulazne tačke ali jedna modal aplikacija. Procjenjujem +20% rada u Fazi 1 zbog Classic support-a.

**Settings: Force editor type** (opciono): možeš isključiti Classic support ako klijent migrira sve na Gutenberg.

### 14.7 Cron strategija

**Real cron preporučen, WP-Cron fallback.**

**Faza 0:**
- Plugin detektuje cron mod (`DISABLE_WP_CRON` constant)
- Settings stranica prikazuje status:
  - ✅ Real cron detected (DISABLE_WP_CRON je `true`)
  - ⚠️ WP-Cron aktivan — preporučujemo real cron za pouzdano weekly skeniranje [link na instrukcije]

**Faza 1.5:**
- Sve cron events registrovane kroz `wp_schedule_event()` (radi sa oba)
- Weekly scan event: `aisa_weekly_scan`, nedjelja 03:00 server time
- Email digest event: `aisa_weekly_digest`, ponedjeljak 08:00
- Performance tracking event: `aisa_track_outcomes`, dnevno 04:00

**Dokumentacija plugina** (`README.md`) sadrži sekciju "Cron Setup" sa:
- Zašto je real cron važan
- Kako provjeriti da li hosting ima cron (`crontab -l` ili pitati hosting support)
- Snippet za crontab:
  ```
  */15 * * * * curl -s https://your-site.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
  ```
- Snippet za `wp-config.php`:
  ```php
  define('DISABLE_WP_CRON', true);
  ```

**Plugin uninstall:**
`uninstall.php` poziva `wp_clear_scheduled_hook()` za sve events (radi sa oba mehanizma).

---

## 15. Ažurirani Acceptance kriterijumi (dopuna sekcije 10)

### Faza 0 — dodatno
- [ ] Plugin slug, namespace, prefix konzistentni svuda
- [ ] CPT auto-discovery radi (test sa fiktivnim CPT)
- [ ] Brand voice fields perzistiraju i ubacuju se u prompt
- [ ] Cron mode detection prikazuje tačan status
- [ ] Bosanski jezik enforcement instrukcije su u prompt template-ima

### Faza 1 — dodatno
- [ ] Gutenberg flow radi end-to-end
- [ ] Classic editor flow radi end-to-end
- [ ] Detekcija editora je tačna (Gutenberg vs Classic post)
- [ ] `BosnianValidator` označava hrvatske/srpske riječi u output-u
- [ ] Per-ability model override radi (test sa različitim modelima)
- [ ] Tagovi se automatski dodjeljuju preko `wp_set_post_terms()` nakon Confirm-a
- [ ] Kategorije se NE mijenjaju automatski (čak ni ako AI predloži)

### Faza 1.5 — dodatno
- [ ] Real cron i WP-Cron rade isto
- [ ] Cron status notice se prikazuje pravilno

---

## 16. Konačan checklist prije sledeće Claude Code sesije

- [x] Plan dokument finaliziran
- [x] Sve odluke iz Open Questions donesene
- [x] Strategija faza dogovorena
- [x] Brand voice strategija jasna
- [x] Bosanski jezik enforcement specifikovan
- [x] Dual editor support (Gutenberg + Classic) potvrđen
- [x] Cron strategija odlučena
- [ ] **Slijedeće:** Otvoriti Claude Code projekt, kreirati prazan repo, ubaciti ovaj plan kao kontekstualni dokument, krenuti sa Sesijom 1 iz sekcije 12


---

## 17. GSC Dashboard (Faza 1.5 — dopuna)

Pun dashboard koji daje editorima pregled stanja sajta na osnovu Google Search Console podataka. Odvojen od "SEO Suggestions Inbox" — Inbox je actionable lista, Dashboard je analitika.

### 17.1 Lokacija
- WordPress admin > "AI SEO" top-level meni
- Default landing screen kad klikneš na meni: Dashboard
- Sub-stranice: Dashboard / Suggestions Inbox / Settings

### 17.2 Layout — 4 widgeta

**Widget 1 — GSC Health Snapshot**
- Ukupne impressions zadnjih 28 dana + trend strelica vs prethodnih 28
- Ukupan CTR sa benchmark linijom
- Prosječna pozicija
- Broj indeksiranih vs ukupan broj članaka
- Sparkline graf za svaku metriku (mini line chart, 90 dana)
- Refresh dugme (manual GSC refresh)

**Widget 2 — Top Movers**
- 3 tabova:
  - "Najveći skok ↑" — članci sa najboljim position improvement
  - "Najveći pad ↓" — članci koji su pali (visibility)
  - "Najbolji CTR" — članci sa CTR >2x benchmark (može poslužiti kao šablon)
- Lista 10 stavki, klik → per-article detail view

**Widget 3 — Suggestions Pipeline**
- Brojači: pending / accepted ove sedmice / waiting for results
- Mini-graf: prosječan impact nakon prihvatanja (zadnjih 30 dana)
- Quick link na Inbox

**Widget 4 — Coverage & Issues**
- GSC Coverage Report integration
- Lista upozorenja: duplicate meta, missing meta, indexing errors
- Filter: only fixable (one-click fix) / all issues

### 17.3 Per-article Detail View

URL: `admin.php?page=ai-seo-dashboard&article={post_id}`

**Layout — tri sekcije:**

**A) Analytics (top)**
- Header sa post title + link na edit
- 2 grafa side-by-side: Position trend (90d) + CTR trend (90d)
- Top 10 queries tabela: query, impressions, clicks, CTR, position, position change

**B) Predložene popravke (middle) — DOZVOLJENO IZ DASHBOARD-A**

Ova sekcija dijeli se na tri kartice, svaka sa svojim approve/regen flow-om:

**Kartica 1: SEO meta polja**
- Meta title (Yoast ili Rank Math, kroz adapter)
  - Trenutno: prikaz postojećeg
  - AI suggestion: editabilni textbox
  - Character counter, GSC kontekst ("Predloženo na osnovu queryja 'X' sa 1240 impressions")
  - Dugmad: `[Pregledaj diff]` `[Approve]` `[Approve uz izmjene]` `[Regenerate]` `[Reject]` `[Snooze 7d]`
- Meta description (isto)
- Focus keyword (isto)

**Kartica 2: AEO polja**
- TL;DR sažetak
- FAQ Q&A parovi
- Glavno pitanje
- Entiteti/teme
- Svako polje ima isti set dugmadi kao gore

**Kartica 3: Tagovi (post_tag taxonomy)**
- AI suggestion: lista "+ dodaj X, Y" i "− ukloni Z"
- Per-tag mini checkbox liste — editor može selektovati koje od predloženih hoće
- Dugmad: `[Approve odabrane]` `[Regenerate]` `[Reject all]`

**C) Sadržaj članka — ZABRANJENO IZ DASHBOARD-A (sekcija "Otvori u editoru")**

Ako weekly scan ili AI analiza detektuje content issue-e:

- Žuta info kartica: "⚠️ AI je detektovao {N} problem(a) u sadržaju ovog članka"
- Lista issue-a sa severity badge-ovima (read-only, samo dijagnostika)
- Veliko dugme: `[Otvori članak u editoru →]`
  - Klik → otvara post edit screen
  - Auto-triggeruje SEO Review modal sa Korak 1 popunjenim tim issue-ima
  - Editor radi auto-fix (Gutenberg) ili copy suggestion (Classic)
  - Confirm flow nastavlja normalno kroz Korak 2 (SEO/AEO)

**Razlog razdvajanja:** Sadržaj članka je intelektualni rad autora. Mijenjanje content-a iz Dashboard-a stvara:
- Concurrency probleme (editor možda gleda članak u drugom tab-u)
- Audit trail komplikacije
- Mnogo veći blast radius pri grešci
- Krši princip "AI mijenja samo meta-omotač, nikad sadržaj"

Meta polja (SEO/AEO/tagovi) imaju mali blast radius — pogrešno je lako rollback-ovati (jedno polje). Content izmjene su mnogo bolnije za vratiti.

**D) Audit history (bottom)**
- Tabela svih dosadašnjih promjena za taj članak
- Polja: datum, korisnik, polje, stara vrijednost (truncated), nova vrijednost (truncated), izvor (Modal / Inbox / Dashboard), GSC kontekst u tom trenutku
- Filter po izvoru
- Klik na red → expandable full diff view

### 17.4 Approve mehanika u Dashboard-u (per-article only)

**Kritično pravilo:** Bulk approve operacije **nisu** dostupne iz Dashboard-a. Svaka approve akcija je per-article, per-field.

Razlozi:
- Sigurnost: editor mora gledati tačno koji članak prihvata
- Audit jasnoća: jedan klik = jedan trag u audit log-u
- Kvalitet: AI prijedlozi se kvalitativno razlikuju po članku, bulk approve zamućuje pažnju
- Editor friction: ako editor mora kliknuti 30 puta za 30 članaka — to je feature, ne bug. Forsira pažljiv pregled.

**Approve flow per-field:**
1. Editor klikne `[Approve]` na konkretnom polju
2. Frontend pošalje `POST /aisa/v1/suggestions/{suggestion_id}/approve`
3. Backend:
   - Capability check (`approve_aisa_suggestions`)
   - Per-post ownership check (`aisa_for_others` za tuđe članke)
   - Rate limit check
   - Snima u Yoast/Rank Math/AEO/tags kroz odgovarajući adapter
   - Update `wp_aisa_suggestions` status = `accepted`, popuni `reviewed_at`, `reviewed_by`, `applied_value`
   - Snima GSC snapshot za buduće impact tracking
   - Triggeruje headless webhook (ako je aktiviran)
4. Frontend toggle: polje pređe u "✅ Applied {datum}" stanje, dugmad nestane (osim Undo dugmeta 5 min)

**"Approve uz izmjene" flow:**
1. Klik → polje se pretvori u editable input sa pred-popunjenim AI predlogom
2. Editor edituje
3. Dugme se promijeni u `[Snimi izmijenjeno]`
4. Snima edited vrijednost (ne originalni AI predlog), audit log označava `manually_edited: true`

**"Undo" funkcionalnost:**
- Nakon Approve, prikaže se "✅ Applied. [Undo]" toast 5 minuta
- Undo vraća staru vrijednost iz audit log-a
- Nakon 5 min, undo nije dostupan iz Dashboard-a (može se ručno editovati u editoru)

### 17.5 Tehničke komponente
- React app, lazy-loaded samo na AI SEO admin stranicama
- Chart.js ili Recharts za grafove
- REST endpoints:
  - `GET /aisa/v1/dashboard/snapshot` — agregirani GSC podaci
  - `GET /aisa/v1/dashboard/top-movers?type=up|down|ctr`
  - `GET /aisa/v1/articles/{id}/analytics` — per-article podaci
  - `GET /aisa/v1/articles/{id}/suggestions` — aktuelni suggestions za članak
  - `POST /aisa/v1/articles/{id}/generate-suggestion` — on-demand AI poziv (sa GSC contextom)
  - `POST /aisa/v1/suggestions/{id}/approve` — apply suggestion
  - `POST /aisa/v1/suggestions/{id}/reject` — mark rejected
  - `POST /aisa/v1/suggestions/{id}/snooze` — snooze 7d
  - `POST /aisa/v1/suggestions/{id}/undo` — rollback (5min window)
- Caching: 1h transient za snapshot, 4h za per-article
- Manual refresh dugme invalida cache

### 17.6 Performance
- Dashboard učitava sve widgete paralelno (Promise.all)
- Skeleton states dok stiže data
- Ako GSC nije konektovan: prikaže call-to-action umjesto praznog widgeta
- Per-article detail view učitava analytics + suggestions + audit history paralelno

---

## 18. Approve workflow — sumirani pregled

Sve approve tačke u plug-inu, na jednom mjestu radi jasnoće:

### 18.1 Approval points

| Kontekst | Šta editor odobrava | Dugmad | Posljedica |
|----------|---------------------|--------|------------|
| Modal Korak 1 (Gutenberg) | Auto-fix issue u sadržaju | `[Primijeni automatski]` | Mijenja Gutenberg blokove |
| Modal Korak 1 (Classic) | Pregleda suggestion za sadržaj | `[Copy suggestion]` | Kopira u clipboard, editor ručno paste-uje |
| Modal Korak 1 (oboje) | Preskače issue | `[Preskoči ovaj]` | Issue se loguje kao "skipped" za audit |
| Modal Korak 1 (oboje) | Regeneriše AI analizu | `[Regeneriši]` | Novi AI poziv, novi issues |
| Modal Korak 2 | Pregleda SEO/AEO polje | `[Prikaži diff]` | Otvara DiffView |
| Modal Korak 2 | Regeneriše polje | `[Regeneriši]` | Novi prijedlog za to polje |
| Modal Korak 2 | Završava cijeli flow | `[Potvrdi SEO]` | Snima sve, zatvara modal, publishuje |
| Inbox | Pregleda weekly prijedlog | `[Prihvati]` | Aplicira promjenu odmah |
| Inbox | Mijenja prijedlog prije apply | `[Prihvati uz izmjene]` | Otvara mini-editor, onda aplicira |
| Inbox | Odbija prijedlog | `[Odbij]` | Status = rejected, ne pojavljuje se ponovo isti |
| Inbox | Odgađa | `[Snooze 7 dana]` | Status = snoozed, vraća se za 7d |
| Dashboard detail (meta polje) | Aplicira meta title/desc/keyword | `[Approve]` | Snima kroz Yoast/Rank Math adapter |
| Dashboard detail (AEO polje) | Aplicira TL;DR / FAQ / pitanje / entitete | `[Approve]` | Snima u AEO meta polja |
| Dashboard detail (tagovi) | Aplicira tag dodavanja/uklanjanja | `[Approve odabrane]` | `wp_set_post_terms()` |
| Dashboard detail (svako polje) | Mijenja AI predlog prije apply | `[Approve uz izmjene]` | Inline editor + snima |
| Dashboard detail (svako polje) | Regeneriše prijedlog | `[Regenerate]` | Novi AI poziv sa GSC contextom |
| Dashboard detail (svako polje) | Odbija prijedlog | `[Reject]` | Status = rejected |
| Dashboard detail (svako polje) | Odgađa | `[Snooze 7d]` | Vraća se za 7d sa novim GSC podacima |
| Dashboard detail (5 min window) | Vraća zadnju Approve akciju | `[Undo]` | Rollback iz audit log-a |
| Dashboard detail (content issues) | Otvara članak u editoru | `[Otvori članak u editoru →]` | **Sadržaj se ne mijenja iz Dashboard-a** |
| Dashboard detail | Generiše novi prijedlog ako ga nema | `[Generate fresh suggestion]` | AI poziv sa najnovijim GSC podacima |

### 18.2 Per-article approve, nikad bulk

**Pravilo:** Sve approve akcije u plug-inu su **per-article, per-field**. Nema bulk approve mehanizma.

Razlog:
- Sigurnost: svaki klik = jedan članak, jedan audit log unos
- Kvalitet: AI prijedlozi se kvalitativno razlikuju po članku, bulk approve zamućuje pažnju
- Friction kao feature: ako editor mora kliknuti 30 puta za 30 članaka, to forsira svjestan pregled
- Manji blast radius pri grešci

**Šta se mijenja u odnosu na ranije verzije plana:**
- ~~Inbox bulk approve~~ — uklonjeno
- ~~Confirmation modal sa "POTVRDI" tekstom~~ — nije potrebno bez bulk-a
- Sve operacije idu jedan po jedan

**Faza 2 može razmotriti bulk** kasnije, ako stvarni use case to opravda, ali ne ulazi u Fazu 1.5.

### 18.3 Sadržaj članka — strogo razdvajanje

Plugin nikad ne mijenja content tijela članka iz Dashboard-a, Inbox-a, ni bilo kog admin screen-a.

Svaka content izmjena mora ići kroz:
1. Editor otvori članak (post edit screen)
2. SEO Review modal se otvori (sa kontekstom ako postoji)
3. Korak 1 — auto-fix (Gutenberg) ili copy-paste (Classic) sa eksplicitnim akcijama editora
4. Korak 2 — SEO/AEO confirm flow
5. Save

Dashboard i Inbox pokazuju content issue-e samo kao **dijagnostiku** + dugme "Otvori članak u editoru".

### 18.3 Audit trail (mini-verzija za Fazu 1.5, puna u Fazi 2)

Tabela `wp_aisa_suggestions` već ima polja:
- `created_at`, `reviewed_at`, `reviewed_by`, `status`
- Faza 2 dodaje `wp_aisa_audit_log` za sve approval akcije


---

## 19. Sigurnost (puna sekcija — zamijenjuje 9.5)

Sigurnost je **first-class concern**, ne afterthought. Plugin barata sa: AI API ključevima (financijska izloženost), Google OAuth tokenima, sadržajem članaka (potencijalno povjerljiv), capability sistemom, eksternim API pozivima. Svaki od ovih je vektor napada.

### 19.1 API ključevi i OAuth tokeni

**Encryption at rest:**
- Sve credentials (OpenAI key, Anthropic key, GSC OAuth tokens) enkriptovani u DB
- Algoritam: AES-256-GCM (authenticated encryption)
- Encryption key derivation: PBKDF2 sa `AUTH_KEY` + `SECURE_AUTH_SALT` iz `wp-config.php` kao base material
- IV (initialization vector) per-encryption, čuva se uz ciphertext
- Ako se `AUTH_KEY` promijeni, postojeći ključevi su nedohvatljivi (feature: invalidates compromised installs)

**UI handling:**
- Settings nikad ne prikazuju plaintext API key
- Polje je masked: `sk-proj-***...***xyz` (prvih 8 + zadnjih 4 znaka)
- Update flow: novo unošenje ključa zamjenjuje stari, prikazuje "Updated" notice
- Test connection dugme za verifikaciju bez izlaganja ključa

**Storage hygiene:**
- Plain text key se **nikad** ne loguje (ni u WP debug log)
- Plain text key se **nikad** ne šalje preko REST API (čak ni adminu)
- Backup tools koji čitaju `wp_options` dobijaju samo ciphertext
- WP-CLI komanda `wp aisa keys:rotate` za rotaciju ključeva

### 19.2 Authentication i authorization

**Nonces:**
- Sve REST i Abilities pozive zahtjevaju `wp_verify_nonce`
- Nonces se osvježavaju sa `wp_create_nonce` u JS state-u
- Lifetime: 12h (WP default)

**Custom capabilities** (kreiraju se pri aktivaciji):

| Capability | Mapirana na default rolu | Dozvoljava |
|------------|-------------------------|------------|
| `manage_aisa` | Administrator | Settings, OAuth, default models, danger zone |
| `use_aisa` | Editor, Author, Administrator | Pokreće modal, generiše prijedloge za vlastite članke |
| `approve_aisa_suggestions` | Editor, Administrator | Prihvata Inbox i Dashboard prijedloge |
| `view_aisa_dashboard` | Editor, Administrator | Vidi GSC Dashboard |
| `aisa_for_others` | Editor, Administrator | Pokreće AI na člancima drugih autora |

**Per-post ownership check:**
```php
// Pseudokod
if (!current_user_can('aisa_for_others') && get_post($post_id)->post_author !== get_current_user_id()) {
    return new WP_Error('forbidden', 'Možeš pokrenuti AI samo na vlastitim člancima');
}
```

**Settings page locked behind `manage_options` + `manage_aisa`** — dva sloja, ne samo jedan.

### 19.3 Input validacija

**AI input (post content prije slanja AI-u):**
- Maksimalna dužina: 8000 tokena (oko 32000 znakova)
- Strip HTML except allowlist: `p, h1-h6, ul, ol, li, strong, em, a` (Gutenberg blocks parsiraju se u ovo)
- **Prompt injection detection:**
  - Lista suspicious paterna: `ignore previous`, `forget the rules`, `system:`, `you are now`, `disregard`, itd.
  - Detected → escape (umotaj u `<user_content>` tag) ili odbij sa specifičnom porukom
  - Logiraj kao security event

**System prompt isolation:**
- System prompt i user content **uvijek** šalju se kao odvojeni `role: system` i `role: user` messages
- Nikad concatenated u jedan string
- Ovo je standardni hardening za OpenAI/Anthropic API-je

**REST input:**
- Sva polja prolaze kroz `register_rest_route` `args` sa `validate_callback` + `sanitize_callback`
- `post_id` → `absint` + post existence check
- `regen_field` → enum (`meta_title`, `meta_description`, ...) sa strict check

### 19.4 Output sanitizacija (AI output prije snimanja)

| Polje | Sanitizacija | Validacija |
|-------|--------------|------------|
| Meta title | `sanitize_text_field` | Max 80 znakova, no HTML |
| Meta description | `sanitize_textarea_field` | Max 200 znakova, no HTML |
| Tag names | `sanitize_text_field` + `wp_strip_all_tags` | Max 50 znakova, max 8 tagova |
| AEO TL;DR | `wp_kses` (allowlist: strong, em) | Max 600 znakova |
| AEO FAQ items | JSON Schema strict: `{question: string, answer: string}` | Max 8 items, Q ≤200, A ≤500 znakova |
| AEO main question | `sanitize_text_field` | Max 150 znakova |
| AEO entities | Array of strings, `sanitize_text_field` per item | Max 10 items, ≤50 per item |

**URL validacija:** Ako AI vrati URL u nekom polju (npr. canonical), `wp_http_validate_url` + provjera da li je istog hosta. Eksterne URL-ove odbijamo.

**JSON output parsing:**
- AI odgovor mora biti validan JSON (system prompt instrukcija)
- Failed parse → retry sa "your last response was not valid JSON" follow-up
- 3 retries, onda fallback message
- Schema validacija sa `JsonSchema` PHP library

### 19.5 Rate limiting (proširenje sekcije 5.7)

**Multi-layer:**

| Sloj | Limit | Resetuje se |
|------|-------|-------------|
| Per article per user | 10 regen | 60 min |
| Per user per day | 50 generations | 24h |
| Per site per day | 200 generations | 24h |
| Per IP per minute | 30 REST poziva | 60s |
| Failed auth per IP | 5 pokušaja | 15 min (zatim 15min cooldown) |

**Implementacija:** WordPress transients sa fingerprint key-evima.

**Notification flow:**
- Soft limit (80% korištenja) → admin notice
- Hard limit → blokiramo akciju, prikažemo poruku
- Repeated abuse → automatic temporary capability removal + email adminu

### 19.6 Webhook security (headless)

**Outgoing webhook (plugin → Next.js):**
- HMAC-SHA256 signature u `X-AISA-Signature` header
- Shared secret čuva se enkriptovan
- Payload uključuje `timestamp` (sprečava replay attacks)
- Timestamp tolerancija: ±5 min
- Frontend mora verifikovati signature i odbiti zahtjev ako:
  - Signature ne valida
  - Timestamp je van tolerancije
  - Payload polja fale

**HTTPS-only:** Settings odbija ne-HTTPS webhook URL-ove.

**Minimal payload:**
```json
{
  "post_id": 1234,
  "slug": "moj-clanak",
  "post_type": "post",
  "action": "seo_confirmed",
  "timestamp": 1747000000,
  "signature": "hmac..."
}
```
Nema sadržaja članka, nema meta polja — frontend ih sam povlači preko REST API.

### 19.7 GSC OAuth security

**OAuth 2.0 hardening:**
- Authorization Code flow sa **PKCE** (Proof Key for Code Exchange)
- `state` parameter generisan sa `wp_generate_password(32, false)` (cryptographically random)
- `state` se verifikuje na callback-u
- Redirect URI je explicit registrovan u Google Cloud Console, ne wildcard
- Scope: samo `https://www.googleapis.com/auth/webmasters.readonly` (minimum)

**Token handling:**
- Access token: keširaн u memoriji ili kratkotrajni transient, ne dugotrajno u DB
- Refresh token: enkriptovan, čuva se u DB
- Refresh token revoke endpoint pozove se pri:
  - Disconnect dugmetu u settings
  - Plugin uninstall (ako user izabere)
  - Detekciji compromise (sumnjiva aktivnost)

### 19.8 Logging i monitoring

**AI activity log** (`wp_aisa_activity_log` tabela):
- Polja: `id`, `user_id`, `post_id`, `ability`, `model_used`, `tokens_in`, `tokens_out`, `latency_ms`, `status`, `error_code`, `created_at`
- **Ne sadrži** prompt content ili AI response (privacy + storage)
- Optional: "Verbose logging" toggle u settings (čuva content, samo za debugging)

**Security event log** (`wp_aisa_security_log`):
- Failed nonce, capability violation, rate limit hit, suspicious input, OAuth failures
- Polja: `id`, `event_type`, `user_id`, `ip_address`, `user_agent`, `details_json`, `severity`, `created_at`
- Retention: 30 dana (configurable)
- Admin notice ako severity = `critical` (npr. brute force pokušaj)

**WP-CLI komande:**
- `wp aisa logs:tail` — live tail security log
- `wp aisa logs:stats` — agregacija po event_type
- `wp aisa keys:test` — verifikuje credentials bez ekspozicije

### 19.9 Privacy i data hygiene

**Opt-in flow:**
- Pri prvom enable-u plugina, prikaže se modal sa:
  - "Plugin šalje sadržaj članaka AI provider-u ({{provider_name}}). Pregledaj privacy policy: {{link}}"
  - Checkbox "Razumijem, slažem se"
  - Bez tog opt-in, AI funkcije su disabled

**Per-post exclusion:**
- Post meta `_aisa_excluded` (boolean) — članak se ne šalje AI-u
- Sidebar checkbox "Exclude this post from AI"
- Bulk exclusion preko post listing screen-a

**Per-post-type exclusion:**
- Settings checkbox za svaki post type "Include in AI" (već u sekciji 14.4)

**Tag-based exclusion:**
- Settings polje "Exclude posts with these tags" (comma-separated)
- Default: `confidential, internal`

**Uninstall behavior:**
- Settings imaju "Danger Zone" sekciju
- Opcija "Delete all data on uninstall" (default OFF)
- Ako ON: pri uninstall briše options, meta, custom tabele, cron events, capabilities

### 19.10 Update i deployment

**Distribution security:**
- GitHub releases sa signed commits + checksums
- `composer.lock` committed
- `package-lock.json` committed
- Build artifacts pre-built (ne traži composer/npm na produkciji)

**Dependency security:**
- `composer audit` u CI/CD
- `npm audit` u CI/CD
- Renovate ili Dependabot za auto-PR-ove na security update-ima
- Plugin verzija prikazuje se u admin footer + dashboard (za incident response)

**Update mechanism:**
- Plugin koristi standardni WP update mehanizam
- Migrations script za DB shema promjene (verzionisan)
- Rollback mehanizam ako migration padne

### 19.11 Security checklist po fazi

**Faza 0:**
- [ ] Encryption sloj implementiran (AES-256-GCM)
- [ ] Custom capabilities kreirane
- [ ] Nonces na svim endpointima
- [ ] Input/output sanitizacija sloj postoji
- [ ] Settings ne izlaže plain text API keys
- [ ] Composer i npm dependencies audited

**Faza 1:**
- [ ] Per-post ownership check na svim AI pozivima
- [ ] Rate limiting aktivan
- [ ] Prompt injection detection aktivan
- [ ] AI output validacija prolazi sve test slučajeve
- [ ] Webhook HMAC signature radi
- [ ] Opt-in modal radi pri prvom enable

**Faza 1.5:**
- [ ] OAuth PKCE flow radi
- [ ] State parameter validacija radi
- [ ] GSC tokens enkriptovani
- [ ] Per-article approve flow radi (Inbox + Dashboard)
- [ ] Undo funkcija radi unutar 5 min prozora
- [ ] Audit log bilježi sve approve akcije sa izvorom (Modal/Inbox/Dashboard)
- [ ] Security event log popunjava se

---

## 20. Compliance ready (za buduće GDPR / lokalne propise)

Iako trenutno nema GDPR potrebe, dizajn postavlja temelj:

- Sve user data je trace-abilna kroz `user_id` foreign keys
- "Export user data" hook (`wp_privacy_export_personal_data`) registrovan ali no-op u Fazi 1
- "Erase user data" hook (`wp_privacy_personal_data_erasers`) registrovan
- Retention policy configurable
- Audit log infrastruktura postoji (Faza 2 ga puni)
- Data Processing Agreement-friendly: jasno definisani data flows u dokumentaciji

---

## 21. Završni rezime — šta plan sada pokriva

Plan je sada kompletan i pokriva:

**Funkcionalnost:**
- Publish flow modal (Gutenberg + Classic editor)
- 4 AI generacije: content analysis, Yoast/Rank Math meta, tags, AEO (TL;DR + FAQ + main question + entities)
- AEO JSON-LD output (klasični WP + headless REST API)
- "Prva objava only" logika
- "Re-run SEO Review" manual trigger
- Regenerate per polje sa diff view
- Auto-fix akcije (Gutenberg)
- Copy suggestion (Classic)
- Headless webhook
- GSC integracija (Faza 1.5)
- Weekly scan + AI suggestions
- SEO Suggestions Inbox (per-article approve)
- GSC Dashboard sa 4 widgeta + per-article detail view (Approve meta/AEO/tagovi direktno iz Dashboard-a, sadržaj samo kroz editor)
- Per-article approve sa Undo (5 min window)
- Performance tracking nakon prihvatanja

**Tehnička infrastruktura:**
- Hibridni AI sloj (WP AI Client + OpenAI + Anthropic)
- SEO adapter (Yoast + Rank Math od starta)
- Abilities API + REST fallback
- Prompt versioning
- Rate limiting (5-layer)
- Failure resilience sa retry i fallback
- Cron infrastruktura (real cron preporučen)

**Sigurnost:**
- Encryption at rest za sve credentials
- Custom capabilities sa per-post ownership
- Prompt injection detection
- HMAC webhook signatures
- OAuth PKCE
- Activity i security logovi
- Multi-layer rate limiting
- Opt-in privacy flow

**Lokalizacija:**
- Eksplicitno bosanski enforcement sa hrvatska/srpska riječ blokadom
- Per-post jezik override
- Brand voice opcionalna polja
- CPT auto-discovery

**Sve odluke iz razgovora ugrađene:**
- ✅ Slug `ai-seo-assistant`, namespace `AiSeoAssistant\`, author Alen Melkic
- ✅ Default model + per-ability override
- ✅ Engleski promptovi sa respond-in instrukcijom + bosanski enforcement
- ✅ Auto-discovery CPT-ova
- ✅ Brand voice optional
- ✅ Gutenberg + Classic editor dual support
- ✅ Real cron preporučen, WP-Cron fallback
- ✅ Faze: 0 (temelji) → 1 (MVP) → 1.5 (intelligence) → 2 (scale)
- ✅ GSC Dashboard sa widgetima
- ✅ Approve workflow sumiran
- ✅ Puna sigurnosna sekcija


---

## 22. Performance i DB optimizacija (kritična sekcija)

Plugin mora imati **mjerljiv, predvidljiv** uticaj na DB i loading. Bez ove sekcije, plugin može izazvati ozbiljne probleme na velikim sajtovima nakon 6-12 mjeseci korištenja.

### 22.1 DB impact procjena

**Custom tabele i procijenjeni rast (sajt sa 8000 članaka, godinu dana):**

| Tabela | Faza | Veličina | Indeksi |
|--------|------|----------|---------|
| `wp_aisa_suggestions` | 1.5 | ~240 MB | `(post_id, status, created_at)`, `(status, created_at)` |
| `wp_aisa_activity_log` | 1.5 | ~120 MB | `(user_id, created_at)`, `(post_id, created_at)` |
| `wp_aisa_security_log` | 1.5 | ~30 MB | `(event_type, created_at)` |
| `wp_aisa_outcomes` | 1.5 | ~20 MB | `(post_id, measured_at)` |
| `wp_aisa_audit_log` | 2 | ~50 MB | `(user_id, created_at)`, `(post_id, created_at)` |

**Postmeta dodatak po članku:** ~3 KB (AEO polja + flags). Beznačajno.

**Options dodatak:** ~50 KB ukupno (settings + tokens + cache).

### 22.2 Retention policy

Sve log tabele imaju automatsku retenciju kroz dnevni cron event `aisa_db_cleanup`:

| Tabela | Default retention | Configurable | Logika |
|--------|------------------|--------------|--------|
| `wp_aisa_activity_log` | 90 dana | Da, 30-365 dana | Hard delete starije |
| `wp_aisa_security_log` | 30 dana | Da, 7-90 dana | Hard delete starije osim `severity = critical` |
| `wp_aisa_suggestions` (rejected) | 60 dana | Da | Hard delete |
| `wp_aisa_suggestions` (expired/snoozed) | 30 dana | Da | Hard delete |
| `wp_aisa_suggestions` (accepted) | Trajno | Ne | Audit purpose |
| `wp_aisa_outcomes` | Trajno | Ne | Long-term tracking |
| `wp_aisa_audit_log` | 2 godine | Da | Compliance |

**Settings UI:** "Database Maintenance" sekcija sa retention slider-ima.

### 22.3 JSON kompresija

Velika JSON polja (gsc_snapshot, details_json) se gzip-uju prije insert-a:

```php
$compressed = gzcompress(json_encode($data), 6);
// pri čitanju:
$data = json_decode(gzuncompress($compressed), true);
```

Štedi 60-80% prostora za strukturirane JSON podatke. Trade-off: ~1-2ms CPU per read/write, ali eliminira potrebu za 200+ MB diskovnog prostora.

### 22.4 Indeksi i query optimizacija

**Kritični indeksi koje migration script kreira:**

```sql
-- wp_aisa_suggestions
CREATE INDEX idx_suggestion_lookup ON wp_aisa_suggestions(post_id, status, created_at);
CREATE INDEX idx_pending ON wp_aisa_suggestions(status, created_at) WHERE status = 'pending';
CREATE INDEX idx_cleanup ON wp_aisa_suggestions(status, created_at);

-- wp_aisa_activity_log
CREATE INDEX idx_user_activity ON wp_aisa_activity_log(user_id, created_at);
CREATE INDEX idx_post_activity ON wp_aisa_activity_log(post_id, created_at);
CREATE INDEX idx_cleanup ON wp_aisa_activity_log(created_at);

-- wp_aisa_security_log
CREATE INDEX idx_event ON wp_aisa_security_log(event_type, created_at);
CREATE INDEX idx_ip ON wp_aisa_security_log(ip_address, created_at);
```

**Query best practices:**
- Nikad `SELECT *` — samo potrebna polja
- Pagination obavezna na svim list query-jima (default LIMIT 50)
- Aggregate query-ji u dashboard koriste materialized views ili keširaju u transients

### 22.5 Caching strategija

| Cache | TTL | Storage | Invalidation |
|-------|-----|---------|--------------|
| Dashboard snapshot | 1h | Transient | Manual refresh dugme |
| GSC podaci per article | 4h | Transient | Manual refresh + on accept |
| AI response cache | 1h | Transient | Po post update |
| Suggestion list | 5 min | Object cache | Po novi insert |
| Prompt templates | Indefinite | Object cache | Po version change |
| Settings | Indefinite | Options cache (WP native) | Po settings save |

**Object cache obavezno:** Plugin se OPTIMALNO ponaša sa Redis/Memcached. Bez external object cache-a, sve fallback-uje na transients (DB).

### 22.6 Frontend loading impact

**Plugin namjerno NEMA frontend JS ili CSS.**

| Hook | Cost | Optimizacija |
|------|------|--------------|
| `init` (autoload) | 5-10ms | PSR-4 autoload, samo loaded klase |
| `wp_head` (AEO JSON-LD) | 2-5ms | Čita iz cached postmeta |
| `template_redirect` | 0ms | Plugin ne hookuje |

**Frontend impact: 7-15ms po request-u.** Praktično nemjerivo.

**Eksplicitna ograničenja:**
- Plugin nikad ne enqueue-uje JS na frontend
- Plugin nikad ne enqueue-uje CSS na frontend
- Plugin ne dodaje AJAX endpointe za posjetioce (samo auth-only REST)
- Plugin ne pravi outbound HTTP request-e tokom frontend rendera

### 22.7 Admin loading impact

| Screen | Cost | Optimizacija |
|--------|------|--------------|
| Bilo koji admin screen | +5-10ms | Plugin autoload |
| Edit post screen | +50ms (init React) | Lazy load React bundle |
| Dashboard | 300-500ms first load | Paralelni REST, cached 1h nakon |
| Settings | 100-200ms | Statična stranica |
| Inbox | 200-400ms | Paginated, max 50 per page |

**React bundle:** ~180 KB gzip. Lazy-loaded **samo** na editor screen-ovima i AI SEO admin stranicama. Ostali admin screen-ovi ga ne učitavaju.

### 22.8 Cron load management

**Weekly scan strategy za velike sajtove:**

Naivno: 8000 članaka × 10s = 22 sata sekvencijalno. Rušilo bi server.

**Batch processing:**
- Cron event `aisa_weekly_scan` pokreće se svakih 15 min
- Po pokretanju: obrađuje 50 članaka
- 96 pokretanja/dan × 50 = **4800 članaka/dan**
- Cijeli sajt obrađen za **~2 dana**
- Naredni weekly cycle počinje za 5 dana

**Resource throttling:**
- Ako server load > 4.0 → preskoči ovaj batch
- Ako WordPress je u "high traffic" mode → odloži za sat
- Memory limit per batch: 128 MB
- Time limit per batch: 60s (sa graceful kill ako pređe)

**Filtri za smanjenje obima:**
- Settings: "Scan only articles with GSC impressions > X" (default 100)
- Settings: "Scan only articles published in last X months" (default 24)
- Settings: "Exclude post types from weekly scan"

**Manual override:**
- Dugme "Pause weekly scan" u settings (instant stop)
- Dugme "Force scan now" za specific batch

### 22.9 AI poziv optimizacija

**Token budgeting:**
- Content truncation: max 8000 tokena input
- Ako članak duži: AI dobija prvih 4000 + zadnjih 2000 tokena + ToC od H2 (skraćivanje pametno)
- Output token limit: 1000 (dovoljno za sve odgovore)

**Parallel calls:**
- 4 AI poziva (analyze + meta + tags + aeo) idu **paralelno** preko Promise.all
- Ukupno vrijeme: max(individual times) ≈ 5-8s
- NE: sekvencijalno 15-25s

**Provider failover:**
- Primary fails → automatski pokušaj sa secondary provider-om (ako konfigurisan)
- Failover dodaje 1-2s, ali eliminira "AI not available" situaciju

**Streaming response (Faza 2):**
- Ako provider podržava streaming (oba podržavaju), modal prikazuje tekst kako stiže
- Korisnik osjeća "brže" iako ukupno vrijeme isto

### 22.10 Database Health widget

Settings stranica → "Database Health" sekcija (kreirana u Fazi 1.5):

**Prikazuje:**
- Trenutna veličina svake plugin tabele
- Broj redova po tabeli
- Datum posljednjeg cleanup-a
- Procjenjeni mjesečni rast

**Dugmad:**
- `[Run cleanup now]` — pokreće retention policy odmah
- `[Optimize tables]` — `OPTIMIZE TABLE` za sve plugin tabele
- `[Export logs]` — CSV export prije cleanup-a (audit)

**Upozorenja:**
- Žuto: ukupna veličina > 500 MB
- Crveno: > 1 GB ili posljednji cleanup > 30 dana

### 22.11 Performance acceptance kriterijumi

**Faza 0:**
- [ ] Plugin autoload < 15ms benchmark
- [ ] DB migration kreira sve indekse
- [ ] PSR-4 autoload, ne `require_all`

**Faza 1:**
- [ ] Frontend page load impact < 15ms (testirano sa Query Monitor)
- [ ] Editor screen impact < 100ms initial load
- [ ] React bundle < 200 KB gzip
- [ ] Settings stranica < 300ms load
- [ ] AEO JSON-LD render < 5ms
- [ ] Plugin ne enqueue-uje ništa na frontend

**Faza 1.5:**
- [ ] Dashboard first load < 500ms (sa praznim cache-om)
- [ ] Dashboard cached load < 100ms
- [ ] Weekly scan batch < 60s (50 članaka)
- [ ] DB cleanup briše stale podatke
- [ ] Database Health widget tačno prikazuje veličine

### 22.12 Benchmark protokol (prije production deploy-a)

Klijent treba uraditi:

1. **Frontend impact test:**
   - Koristi Query Monitor + GTmetrix prije i poslije aktivacije plugina
   - Test sa cache off (real impact) i cache on (production scenario)
   - Pass: < 20ms dodatak, < 2 dodatna query-ja

2. **DB growth test (preporučeno):**
   - Mjeri veličinu plugin tabela nakon 1 sedmice i 1 mjeseca korištenja
   - Ekstrapoliraj godišnji rast
   - Pass: < 1GB ekstrapolirano za godinu

3. **AI poziv latency test:**
   - 100 simuliranih content analiza
   - Pass: p95 < 10s, p99 < 15s

4. **Concurrent user test:**
   - 10 editora istovremeno koriste plugin
   - Pass: nema deadlock-a, AI pozivi se ne blokiraju međusobno


---

## 23. Git workflow

Jednostavna struktura, bez komplikacija.

### 23.1 Branch struktura

```
main          → production, stabilan kod
dev/main      → integracija, sve faze se ovdje spajaju
dev/faza-X    → radni branch za trenutnu fazu (jedan u jedno vrijeme)
fix/xxx       → samo ako treba (bug fix izvan tekuće faze)
```

### 23.2 Tok rada za svaku fazu

1. Provjeri `dev/main`, pull latest
2. Kreiraj `dev/faza-X` iz `dev/main`
3. Push branch na remote
4. Radi sve sesije faze u tom branchu, commit per sesija
5. Po završetku faze: PR `dev/faza-X` → `dev/main`
6. Nakon merge-a, obriši `dev/faza-X` (lokalno i remote)
7. Sljedeća faza počinje iz Koraka 1

### 23.3 Commit poruke

Format: `tip(faza-X): kratak opis`

Primjeri:
```
feat(faza-0): sesija 1 - plugin bootstrap i settings
feat(faza-0): sesija 2 - SEO adapter (Yoast + Rank Math)
feat(faza-1): sesija 3 - publish detection i React entry
feat(faza-1): sesija 4 - ReviewModal komponenta
fix(faza-1): popravka DiffView komponente
```

### 23.4 Pull Request

Claude Code automatski otvara PR na kraju faze sa template-om:
- Naslov: `Faza X: kratak opis`
- Body: link na relevantne sekcije plana + acceptance checklist
- Reviewer: Alen Melkic

**Claude Code NE mergeuje** — uvijek čeka tvoju potvrdu.

### 23.5 Production release

Merge `dev/main` → `main` radi se **ručno od strane Alena**, ne Claude Code.

Verzije:
- `v0.1.0` nakon Faze 1
- `v0.2.0` nakon Faze 1.5
- `v1.0.0` nakon Faze 2

### 23.6 Fix branchovi

Koriste se samo kad postoji bug koji treba popraviti izvan tekuće faze (npr. bug u već merge-ovanom kodu dok radimo sljedeću fazu).

Naming: `fix/{kratak-opis}`, npr. `fix/yoast-adapter-meta-desc`

Tok: iz `dev/main` → fix → PR → merge u `dev/main` → cherry-pick u `main` ako je hitno.

### 23.7 Claude Code instrukcije

Na početku svake sesije, Claude Code prati:

```
1. git checkout dev/faza-{trenutna-faza}
2. git pull origin dev/faza-{trenutna-faza}
3. Implementira sesiju iz plana
4. git add . && git commit -m "feat(faza-X): sesija Y - opis"
5. git push origin dev/faza-{trenutna-faza}
```

Na kraju cijele faze:
```
1. Provjerava da su sve sesije commit-ovane
2. gh pr create --base dev/main --head dev/faza-X --title "..." --body "..."
3. Čeka korisnikovu potvrdu prije ikakvog merge-a
```

