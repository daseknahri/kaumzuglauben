<?php
/**
 * KaumZuGlauben site seed — run by docker/site-init.sh via `wp eval-file` on every start.
 *
 * Idempotent: the brand/site setup runs once per KZ_SEED_VERSION, and each article bundle is imported once
 * (tracked in the kz_imported_bundles option), so a restart mid-import simply resumes.
 * Brand identity is read from content/site-profile.json when present (playbook §1.2); the literals below are
 * the fallback and must stay in sync with that file.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KZ_SEED_VERSION = 1;
const KZ_DATA         = '/opt/site/data';
const KZ_BRAND_DIR    = WP_CONTENT_DIR . '/themes/kaumzuglauben/assets/brand';
const KZ_AUTHOR_BIO   = 'Das KaumZuGlauben Team jagt die unglaublichsten wahren Fakten der Welt — und prüft jeden einzelnen gegen verlässliche Quellen, bevor er hier erscheint. Was sich nicht belegen lässt, wird nicht veröffentlicht: Staunen ja, Erfinden nein.';

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function kz_log( $msg ) { WP_CLI::log( '[seed] ' . $msg ); }

function kz_profile() {
	static $p = null;
	if ( null === $p ) {
		$p = array();
		$f = '/opt/site/content/site-profile.json';
		if ( is_readable( $f ) ) {
			$j = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled repo file.
			if ( is_array( $j ) ) { $p = $j; }
		}
	}
	return $p;
}

function kz_site_email() {
	$e = trim( (string) getenv( 'SITE_EMAIL' ) );
	if ( '' !== $e ) { return $e; }
	$p = kz_profile();
	return (string) ( $p['brand']['site_email'] ?? 'daseknahri@gmail.com' );
}

function kz_admin_id() {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	return $admins ? (int) $admins[0] : 1;
}

/* Copy a bundled brand file into the media library once (keyed by filename). */
function kz_brand_media( $file, $title ) {
	$found = get_posts( array( 'post_type' => 'attachment', 'meta_key' => '_kz_brand', 'meta_value' => $file, 'fields' => 'ids', 'numberposts' => 1 ) );
	if ( $found ) { return (int) $found[0]; }
	$tmp = wp_tempnam( $file );
	copy( KZ_BRAND_DIR . '/' . $file, $tmp );
	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0, $title );
	if ( is_wp_error( $id ) ) { kz_log( 'media failed: ' . $file . ' ' . $id->get_error_message() ); return 0; }
	update_post_meta( $id, '_kz_brand', $file );
	return (int) $id;
}

function kz_page( $slug, $title, $html, $status = 'publish' ) {
	$p = get_page_by_path( $slug );
	$data = array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $html, 'post_status' => $status, 'post_author' => kz_admin_id() );
	if ( $p ) { $data['ID'] = $p->ID; return (int) wp_update_post( wp_slash( $data ) ); }
	return (int) wp_insert_post( wp_slash( $data ) );
}

/* The editorial team is the site's author: display name, bio and the brand mark as avatar
   (feeds byline, author box and the Person schema). No invented persona. */
function kz_seed_author() {
	$uid = kz_admin_id();
	wp_update_user( array( 'ID' => $uid, 'display_name' => 'KaumZuGlauben Team', 'nickname' => 'KaumZuGlauben Team', 'description' => KZ_AUTHOR_BIO ) );
	$avatar = kz_brand_media( 'kaumzuglauben-icon-512.png', 'KaumZuGlauben' );
	if ( $avatar ) { update_user_meta( $uid, 'vr_author_avatar', $avatar ); }
}

function kz_categories() {
	$p = kz_profile();
	if ( ! empty( $p['categories'] ) && is_array( $p['categories'] ) ) {
		$out = array();
		foreach ( $p['categories'] as $c ) { $out[ $c['slug'] ] = array( $c['name'], $c['description'] ); }
		return $out;
	}
	return array(
		'geschichte-ereignisse' => array( 'Geschichte & Ereignisse', 'Die unglaublichsten Momente der Geschichte: Wendepunkte, Katastrophen, Zufälle und Ereignisse, die kaum jemand kennt — und die doch die Welt verändert haben.' ),
		'natur-tiere'           => array( 'Natur & Tiere', 'Fakten aus der Natur, die kein Drehbuchautor erfinden könnte: Tiere mit Superkräften, Pflanzen mit Gedächtnis, Wetter, das es eigentlich nicht geben dürfte.' ),
		'mensch-koerper'        => array( 'Mensch & Körper', 'Dein Körper kann Dinge, die du nicht für möglich hältst: Sinne, Reflexe und Fähigkeiten, die die Wissenschaft bestätigt hat.' ),
		'alltag-mythen'         => array( 'Alltag & Mythen', 'Was du jeden Tag benutzt, glaubst oder weitererzählst — und was davon schlicht nicht stimmt. Alltagsmythen im Faktencheck.' ),
		'rekorde-kurioses'      => array( 'Rekorde & Kurioses', 'Die größten, kleinsten, ältesten und verrücktesten Rekorde der Welt — und die kuriosen Geschichten dahinter. Alles dokumentiert, nichts erfunden.' ),
	);
}

function kz_seed_site() {
	$p    = kz_profile();
	$name = (string) ( $p['brand']['name'] ?? 'KaumZuGlauben' );
	$tag  = (string) ( $p['brand']['tagline'] ?? 'Unglaubliche Fakten, die wirklich stimmen.' );
	$mail = kz_site_email();

	update_option( 'blogname', $name );
	update_option( 'blogdescription', $tag );
	update_option( 'admin_email', $mail );
	update_option( 'WPLANG', 'de_DE' );
	update_option( 'timezone_string', 'Europe/Berlin' );
	update_option( 'date_format', 'j. F Y' );
	update_option( 'time_format', 'H:i' );
	update_option( 'start_of_week', 1 );
	update_option( 'posts_per_page', 12 );
	update_option( 'default_comment_status', 'closed' );
	update_option( 'default_ping_status', 'closed' );
	update_option( 'blog_public', 1 );
	update_option( 'permalink_structure', '/%postname%/' );

	/* Theme options: one editorial voice, and the theme's nouns read "article". */
	set_theme_mod( 'vr_byline_author', true );
	set_theme_mod( 'vr_noun', 'article' );

	$icon = kz_brand_media( 'kaumzuglauben-icon-512.png', 'KaumZuGlauben Icon' );
	if ( $icon ) { update_option( 'site_icon', $icon ); }
	$logo = kz_brand_media( 'kaumzuglauben-logo.png', 'KaumZuGlauben Logo' );
	if ( $logo ) { set_theme_mod( 'custom_logo', $logo ); update_option( 'site_logo', $logo ); }

	/* WordPress sample content. */
	foreach ( array( array( 'hello-world', 'post' ), array( 'sample-page', 'page' ) ) as $x ) {
		$sp = get_page_by_path( $x[0], OBJECT, $x[1] );
		if ( $sp ) { wp_delete_post( $sp->ID, true ); }
	}

	/* Categories — slugs fixed so the mood colours in the child theme CSS match. */
	$cats = kz_categories();
	foreach ( $cats as $slug => $c ) {
		$t = get_term_by( 'slug', $slug, 'category' );
		if ( ! $t ) { $t = get_term_by( 'name', $c[0], 'category' ); }
		if ( $t ) { wp_update_term( $t->term_id, 'category', array( 'name' => $c[0], 'slug' => $slug, 'description' => $c[1] ) ); }
		else { wp_insert_term( $c[0], 'category', array( 'slug' => $slug, 'description' => $c[1] ) ); }
	}
	$news = get_term_by( 'slug', 'geschichte-ereignisse', 'category' );
	if ( $news ) { update_option( 'default_category', (int) $news->term_id ); }
	$uncat = get_term_by( 'slug', 'uncategorized', 'category' );
	if ( $uncat ) { wp_update_term( $uncat->term_id, 'category', array( 'name' => 'Allgemein', 'slug' => 'allgemein' ) ); }

	/* Pages — trust/legal set for a German SEO + AdSense site: Über uns, Kontakt, Impressum (§ 5 DDG),
	   Datenschutz (registered as wp_page_for_privacy_policy), Nutzungsbedingungen (incl. Haftungsausschluss),
	   Redaktionsrichtlinien, Cookie-Richtlinie. Structure mirrors the sibling German sites. */
	kz_page( 'ueber-uns', 'Über uns',
		'<p><strong>KaumZuGlauben</strong> sammelt die unglaublichsten wahren Fakten der Welt: Geschichten, bei denen man zweimal hinschauen muss — und die trotzdem stimmen. Unser Versprechen steht im Untertitel: <em>Unglaubliche Fakten, die wirklich stimmen.</em></p>'
		. '<p>Entstanden ist KaumZuGlauben aus einer einfachen Beobachtung: In den deutschen Facebook-Gruppen werden vor allem die Geschichten geteilt, die einen staunen lassen — der Mann, der einen Flugzeugabsturz aus 10.000 Metern überlebte, der Oktopus, der aus seinem Aquarium ausbricht, der Rekord, den seit 50 Jahren niemand knackt. Genau solche Geschichten findest du hier — jeden Tag neu, aus fünf Rubriken: <a href="/category/geschichte-ereignisse/">Geschichte &amp; Ereignisse</a>, <a href="/category/natur-tiere/">Natur &amp; Tiere</a>, <a href="/category/mensch-koerper/">Mensch &amp; Körper</a>, <a href="/category/alltag-mythen/">Alltag &amp; Mythen</a> und <a href="/category/rekorde-kurioses/">Rekorde &amp; Kurioses</a>.</p>'
		. '<h2>Unser Versprechen: geprüft, nicht erfunden</h2><p>Jeder Fakt auf dieser Seite wird vor der Veröffentlichung gegen verlässliche Quellen geprüft — Nachschlagewerke, offizielle Rekord- und Behördenunterlagen, wissenschaftliche Veröffentlichungen und seriöse Berichterstattung. Lässt sich eine Behauptung nicht belegen, erscheint sie nicht. Steht ein Detail nur in einer einzigen Quelle, sagen wir das dazu. Und wenn sich ein Mythos als falsch herausstellt, schreiben wir genau das — auch das ist ein Fakt. Wie wir arbeiten, steht in unseren <a href="/redaktionsrichtlinien/">Redaktionsrichtlinien</a>.</p>'
		. '<h2>Fehler gefunden?</h2><p>Trotz aller Sorgfalt kann sich ein Fehler einschleichen — ein vertauschtes Datum, eine überholte Rekordzahl. Sag uns über die <a href="/kontakt/">Kontaktseite</a> Bescheid: Bestätigte Fehler korrigieren wir umgehend und vermerken die Korrektur im Artikel.</p>' );
	kz_page( 'kontakt', 'Kontakt',
		'<p>Du hast eine Frage zu einem Artikel, einen Fehler entdeckt oder einen unglaublichen Fakt, den wir prüfen sollen? Wir lesen jede Nachricht.</p>'
		. '<p>Schreib dem Team an <a href="mailto:' . esc_attr( $mail ) . '">' . esc_html( $mail ) . '</a>.</p>'
		. '<p>Bei Korrekturen hilft uns der Link zum Artikel und ein kurzer Hinweis, was nicht stimmt — bestätigte Fehler beheben wir umgehend und vermerken die Korrektur im Artikel. Fakt-Vorschläge prüfen wir gegen unsere Quellen; was sich nicht belegen lässt, können wir leider nicht veröffentlichen.</p>' );
	$privacy = kz_page( 'datenschutz', 'Datenschutzerklärung',
		'<h2>1. Verantwortlicher</h2><p>Die Kontaktdaten des Verantwortlichen findest du im <a href="/impressum/">Impressum</a>.</p>'
		. '<h2>2. Hosting und Server-Logfiles</h2><p>Beim Aufruf dieser Website werden technisch notwendige Daten (z. B. IP-Adresse, Datum und Uhrzeit, aufgerufene Seite, Browser) in Server-Logfiles verarbeitet, um den sicheren Betrieb zu gewährleisten (Art. 6 Abs. 1 lit. f DSGVO).</p>'
		. '<h2>3. Schriftarten</h2><p>Die verwendeten Schriftarten werden lokal von unserem Server geladen. Es findet keine Verbindung zu Servern von Google Fonts statt.</p>'
		. '<h2>4. Cookies und Einwilligung</h2><p>Wir und unsere Partner nutzen Cookies und ähnliche Technologien, um die Website zu betreiben, die Nutzung zu verstehen und — soweit Werbung geschaltet wird — Anzeigen auszuliefern und zu messen. Nicht notwendige Cookies werden nur mit deiner Einwilligung gesetzt, die über eine von Google zertifizierte Einwilligungsplattform (Google Privacy &amp; Messaging) eingeholt wird und die du dort jederzeit widerrufen oder anpassen kannst. Details stehen in unserer <a href="/cookie-richtlinie/">Cookie-Richtlinie</a>.</p>'
		. '<h2>5. Werbung (Google AdSense)</h2><p>Diese Website finanziert sich über Werbung von Google AdSense (Google Ireland Ltd., Gordon House, Barrow Street, Dublin 4, Irland; Publisher-ID pub-6869205417923902). Google kann Cookies und ähnliche Technologien einsetzen, um Anzeigen auszuliefern und zu messen. Personalisierte Werbung erfolgt nur mit deiner Einwilligung (Art. 6 Abs. 1 lit. a DSGVO). Du kannst personalisierte Werbung außerdem in den <a href="https://www.google.com/settings/ads" rel="nofollow noopener">Google-Anzeigeneinstellungen</a> deaktivieren. Weitere Informationen: <a href="https://policies.google.com/technologies/partner-sites" rel="nofollow noopener">So nutzt Google Daten von Websites seiner Partner</a>.</p>'
		. '<h2>6. Besucherstatistik</h2><p>Soweit auf dieser Website eine anonyme Reichweitenmessung (Histats) aktiviert ist, werden beim Seitenaufruf technische Daten wie IP-Adresse, Browser, Gerät, Referrer und aufgerufene Seite übertragen und zu Statistiken zusammengefasst. Rechtsgrundlage ist unser berechtigtes Interesse an der Auswertung der Nutzung (Art. 6 Abs. 1 lit. f DSGVO), soweit Cookies gesetzt werden, deine Einwilligung (§ 25 TDDDG).</p>'
		. '<h2>7. Teilen-Schaltflächen</h2><p>Die Schaltflächen „Auf Facebook teilen“ und „Per WhatsApp senden“ sind einfache Links. Daten werden erst übertragen, wenn du sie anklickst.</p>'
		. '<h2>8. Deine Rechte</h2><p>Du hast das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch sowie das Recht auf Beschwerde bei einer Datenschutz-Aufsichtsbehörde. Für Anfragen schreib uns an <a href="mailto:' . esc_attr( $mail ) . '">' . esc_html( $mail ) . '</a>.</p>'
		. '<h2>9. Änderungen</h2><p>Wir können diese Erklärung anpassen, wenn sich die Verarbeitung ändert; die jeweils aktuelle Fassung steht auf dieser Seite.</p>' );
	update_option( 'wp_page_for_privacy_policy', $privacy );
	/* Impressum (§ 5 DDG / § 18 MStV) — operator block mirrors the sibling German sites
	   (owner-supplied details on Deutsche Rezepte, 2026-10-02); nothing invented here. */
	kz_page( 'impressum', 'Impressum',
		'<h2>Angaben gemäß § 5 DDG</h2>'
		. '<p>Dasek Nahri<br>Boukhalef<br>90090 Tanger<br>Marokko</p>'
		. '<h2>Kontakt</h2><p>E-Mail: <a href="mailto:' . esc_attr( $mail ) . '">' . esc_html( $mail ) . '</a></p>'
		. '<h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2><p>Dasek Nahri, Anschrift wie oben</p>'
		. '<h2>Hinweis zu unseren Inhalten</h2><p>Jeder Fakt wird vor der Veröffentlichung gegen verlässliche Quellen geprüft und redaktionell kontrolliert. Zahlen, Rekorde und Angaben können sich im Lauf der Zeit ändern; bestätigte Fehler korrigieren wir umgehend und vermerken die Korrektur im Artikel.</p>'
		. '<h2>Haftung für Links</h2><p>Unsere Seiten können Links zu externen Websites enthalten, auf deren Inhalte wir keinen Einfluss haben. Für diese Inhalte ist stets der jeweilige Anbieter verantwortlich.</p>'
		. '<h2>Verbraucherstreitbeilegung</h2><p>Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>' );
	kz_page( 'nutzungsbedingungen', 'Nutzungsbedingungen',
		'<p>Mit dem Zugriff auf KaumZuGlauben (die „Website“) erklärst du dich mit diesen Nutzungsbedingungen einverstanden. Wenn du nicht einverstanden bist, nutze die Website bitte nicht.</p>'
		. '<h2>Zulässige Nutzung</h2><p>Du darfst die Website nur für rechtmäßige, persönliche Zwecke nutzen. Es ist untersagt, die Website zu beschädigen, zu beeinträchtigen oder zu stören, andere Besucher zu behindern, unbefugten Zugriff zu versuchen oder Inhalte ohne unsere Erlaubnis automatisiert auszulesen (Scraping).</p>'
		. '<h2>Geistiges Eigentum</h2><p>Sofern nicht anders vermerkt, sind die Artikel, Texte und das Design der Website urheberrechtlich geschützt und Eigentum von KaumZuGlauben. Private Nutzung ist willkommen; eine Vervielfältigung, Wiederveröffentlichung oder kommerzielle Nutzung bedarf der vorherigen schriftlichen Zustimmung. Namen, Marken und Rekorde Dritter bleiben Eigentum der jeweiligen Inhaber.</p>'
		. '<h2>Haftungsausschluss</h2><p>Die Website und ihre Inhalte werden „wie besehen“ und ohne Gewährleistung bereitgestellt. Wir prüfen jeden Fakt mit großer Sorgfalt gegen verlässliche Quellen und kennzeichnen Unsicherheiten — eine Garantie für Vollständigkeit, Richtigkeit und Aktualität können wir dennoch nicht übernehmen; Rekorde werden gebrochen und Erkenntnisse ändern sich. Verlasse dich bei wichtigen Entscheidungen nicht allein auf unsere Inhalte.</p>'
		. '<h2>Haftungsbeschränkung</h2><p>Soweit gesetzlich zulässig, haftet KaumZuGlauben nicht für Schäden, die aus der Nutzung der Website oder dem Vertrauen auf ihre Inhalte entstehen.</p>'
		. '<h2>Externe Links</h2><p>Die Website kann auf Websites Dritter verlinken, die wir nicht kontrollieren. Für deren Inhalte und Praktiken sind wir nicht verantwortlich.</p>'
		. '<h2>Änderungen</h2><p>Wir können diese Bedingungen von Zeit zu Zeit anpassen; Änderungen gelten ab ihrer Veröffentlichung auf dieser Seite.</p>' );
	kz_page( 'redaktionsrichtlinien', 'Redaktionsrichtlinien',
		'<p>Diese Seite beschreibt, wie KaumZuGlauben seine Inhalte erstellt — und welchen Maßstab wir an jeden einzelnen Fakt legen.</p>'
		. '<h2>Was wir veröffentlichen</h2><p>Unglaubliche, aber wahre Fakten in fünf Rubriken: Geschichte &amp; Ereignisse, Natur &amp; Tiere, Mensch &amp; Körper, Alltag &amp; Mythen sowie Rekorde &amp; Kurioses. Jeder Artikel erzählt einen Fakt und die Geschichte dahinter.</p>'
		. '<h2>Quellen</h2><p>Jeder Fakt wird vor der Veröffentlichung gegen verlässliche Quellen geprüft: Nachschlagewerke und Enzyklopädien, offizielle Unterlagen (etwa von Rekordorganisationen, Behörden und Museen), wissenschaftliche Veröffentlichungen und seriöse Berichterstattung. Wo ein Detail nur auf einer einzigen Quelle beruht, machen wir das im Artikel erkennbar.</p>'
		. '<h2>Mythen und Gerüchte</h2><p>Vieles, was im Netz kursiert, ist schlicht falsch — das ist der Stoff unserer Rubrik Alltag &amp; Mythen. Mythen, Gerüchte und unbestätigte Behauptungen kennzeichnen wir immer als solche. Wir stellen eine Vermutung niemals als Tatsache dar und erfinden keine Zahlen, Zitate oder Ereignisse. Was sich nicht belegen lässt, wird nicht veröffentlicht.</p>'
		. '<h2>Unabhängigkeit</h2><p>Unsere Auswahl trifft allein die Redaktion. Werbung finanziert die Website, kauft aber keine Inhalte; bezahlte oder gesponserte Beiträge würden klar gekennzeichnet.</p>'
		. '<h2>Korrekturen</h2><p>Wenn wir etwas falsch dargestellt haben, korrigieren wir es umgehend und vermerken wesentliche Korrekturen im Artikel. Hinweise auf Fehler nehmen wir über die <a href="/kontakt/">Kontaktseite</a> entgegen.</p>'
		. '<h2>Bilder</h2><p>Unsere Markenbilder sind eigene Kreationen. Artikelbilder erstellen wir selbst; wir geben fremde Fotos nicht als unsere aus.</p>' );
	kz_page( 'cookie-richtlinie', 'Cookie-Richtlinie',
		'<p>Diese Cookie-Richtlinie erklärt, wie KaumZuGlauben Cookies und ähnliche Technologien nutzt. Sie ergänzt unsere <a href="/datenschutz/">Datenschutzerklärung</a>.</p>'
		. '<h2>Was Cookies sind</h2><p>Cookies sind kleine Textdateien, die auf deinem Gerät gespeichert werden, wenn du eine Website besuchst. Sie helfen Websites zu funktionieren, sich Einstellungen zu merken und zu verstehen, wie Seiten genutzt werden. Ähnliche Technologien sind z. B. Web-Beacons und Pixel. Cookies können von uns (Erstanbieter) oder von Dritten gesetzt werden, deren Dienste wir nutzen.</p>'
		. '<h2>Welche Cookies wir nutzen</h2><ul><li><strong>Notwendige Cookies.</strong> Für den Betrieb und die Sicherheit der Website erforderlich, einschließlich des Speicherns deiner Cookie-Auswahl.</li><li><strong>Statistik-Cookies.</strong> Helfen uns in zusammengefasster Form zu verstehen, welche Seiten gelesen werden und wie Besucher sich auf der Website bewegen.</li><li><strong>Werbe-Cookies.</strong> Soweit Werbung geschaltet wird, nutzen Partner wie Google Cookies, um Anzeigen auszuliefern, ihre Häufigkeit zu begrenzen und ihre Leistung zu messen; mit deiner Einwilligung können Anzeigen auf Grundlage deiner Besuche auf dieser und anderen Websites personalisiert werden.</li></ul>'
		. '<h2>Einwilligung</h2><p>Für Besucher in der EU, im EWR, in der Schweiz und im Vereinigten Königreich werden nicht notwendige Cookies — Statistik und personalisierte Werbung — nur mit deiner Einwilligung gesetzt, die über eine von Google zertifizierte Einwilligungsplattform eingeholt wird. Du kannst deine Einwilligung dort oder in den Browsereinstellungen jederzeit widerrufen oder ändern.</p>'
		. '<h2>Cookies kontrollieren</h2><p>Die meisten Browser lassen dich Cookies in den Einstellungen einsehen, blockieren und löschen. Das Blockieren einiger Cookies kann die Funktion der Website einschränken. Personalisierte Werbung kannst du in den <a href="https://www.google.com/settings/ads" rel="nofollow noopener">Google-Anzeigeneinstellungen</a> deaktivieren.</p>'
		. '<h2>Fragen</h2><p>Fragen zu Cookies beantworten wir über die <a href="/kontakt/">Kontaktseite</a>.</p>' );

	/* Primary menu: the content pillars. */
	$menu = wp_get_nav_menu_object( 'Primary' );
	$mid  = $menu ? $menu->term_id : wp_create_nav_menu( 'Primary' );
	if ( ! $menu ) {
		foreach ( array_keys( $cats ) as $slug ) {
			$t = get_term_by( 'slug', $slug, 'category' );
			if ( $t ) { wp_update_nav_menu_item( $mid, 0, array( 'menu-item-object' => 'category', 'menu-item-object-id' => $t->term_id, 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish' ) ); }
		}
	}
	/* Footer menu: the trust/legal pages. */
	$fmenu = wp_get_nav_menu_object( 'Footer' );
	$fmid  = $fmenu ? $fmenu->term_id : wp_create_nav_menu( 'Footer' );
	if ( ! $fmenu ) {
		foreach ( array( 'ueber-uns', 'kontakt', 'redaktionsrichtlinien', 'datenschutz', 'cookie-richtlinie', 'nutzungsbedingungen', 'impressum' ) as $slug ) {
			$pg = get_page_by_path( $slug );
			if ( $pg ) { wp_update_nav_menu_item( $fmid, 0, array( 'menu-item-object' => 'page', 'menu-item-object-id' => $pg->ID, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) ); }
		}
	}
	$locs = get_theme_mod( 'nav_menu_locations', array() );
	foreach ( get_registered_nav_menus() as $loc => $label ) {
		if ( false !== strpos( $loc, 'primary' ) || 'menu-1' === $loc ) { $locs[ $loc ] = $mid; }
		if ( false !== strpos( $loc, 'footer' ) ) { $locs[ $loc ] = $fmid; }
	}
	set_theme_mod( 'nav_menu_locations', $locs );

	/* Drop WordPress's default sidebar widgets (English 'Recent Posts', Archives, Meta…); the theme renders its own
	   latest-articles + topics blocks when the sidebar is empty. */
	$sw = (array) get_option( 'sidebars_widgets', array() );
	foreach ( $sw as $k => $v ) { if ( is_array( $v ) && 'wp_inactive_widgets' !== $k ) { $sw[ $k ] = array(); } }
	update_option( 'sidebars_widgets', $sw );

	kz_seed_author();

	flush_rewrite_rules( false );
	update_option( 'kz_seed_version', KZ_SEED_VERSION );
	kz_log( 'site seeded' );
}

/* AdSense, two-stage (playbook §5). The publisher id is the getemoji.site family's, shared with the sibling
   subdomains. Stage 1 (ADSENSE_ENABLE!=1): ads.txt is served and the plugin stays OFF, so the site can be
   added + reviewed in AdSense without showing a single ad. Stage 2 (ADSENSE_ENABLE=1): Auto ads loader only —
   no manual slot ids exist for this site yet, so no manual units are configured here.
   Falls back to the site publisher when the variable arrives empty (Coolify passes declared-but-unset
   variables through as empty strings). */
const KZ_ADSENSE_DEFAULT = 'ca-pub-6869205417923902';
function kz_apply_ads() {
	$client = trim( (string) getenv( 'ADSENSE_CLIENT' ) );
	if ( '' === $client ) { $client = KZ_ADSENSE_DEFAULT; }
	if ( 'off' === $client || ! preg_match( '/^ca-pub-\d{10,20}$/', $client ) ) { return; }
	update_option( 'wpap_ads_txt', 'google.com, ' . str_replace( 'ca-', '', $client ) . ', DIRECT, f08c47fec0942fa0' );
	$ads = (array) get_option( 'wpap_ads_inject', array() );
	if ( '1' === trim( (string) getenv( 'ADSENSE_ENABLE' ) ) ) {
		$ads['enabled']   = 1;
		$ads['scope_all'] = 1;
		$ads['auto_code'] = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . $client . '" crossorigin="anonymous"></script>';
	} else {
		$ads['enabled'] = 0;
	}
	update_option( 'wpap_ads_inject', $ads );
}

/* Article bundles (data/bundles/*.zip: posts.json + images) are imported once, via the plugin publisher.
   Empty at launch — the first content batch drops zips in and restarts. */
function kz_import_bundles() {
	if ( ! function_exists( 'wpap_publish_article' ) ) { kz_log( 'plugin not loaded — skipping import' ); return; }
	$done  = (array) get_option( 'kz_imported_bundles', array() );
	$zips  = glob( KZ_DATA . '/bundles/*.zip' ) ?: array();
	sort( $zips );
	$admin = kz_admin_id();
	wp_set_current_user( $admin );
	foreach ( $zips as $zip ) {
		$name = basename( $zip );
		if ( in_array( $name, $done, true ) ) { continue; }
		$work = trailingslashit( get_temp_dir() ) . 'kz-' . sanitize_file_name( $name );
		$z = new ZipArchive();
		if ( true !== $z->open( $zip ) ) { kz_log( "cannot open $name" ); continue; }
		$z->extractTo( $work );
		$z->close();
		$items = json_decode( (string) file_get_contents( $work . '/posts.json' ), true );
		$n = 0;
		foreach ( (array) $items as $item ) {
			$slug = sanitize_title( $item['slug'] ?? $item['title'] ?? '' );
			if ( $slug && get_page_by_path( $slug, OBJECT, 'post' ) ) { continue; }   /* resume-safe */
			$opts = array( 'default_parts' => 1, 'schedule_window' => 0, 'author' => $admin );
			$img  = isset( $item['image'] ) ? realpath( $work . '/' . ltrim( (string) $item['image'], '/' ) ) : false;
			if ( $img && 0 === strpos( $img, realpath( $work ) ) ) { $opts['local_image_path'] = $img; }
			$id = wpap_publish_article( $item, $opts );
			if ( is_wp_error( $id ) ) { kz_log( 'skip: ' . $id->get_error_message() ); continue; }
			/* build-v9 derives the permalink from the title and ignores item slug — pin the
			   planned slug so the resume check above and [[link:slug]] tokens stay valid. */
			if ( $slug ) { wp_update_post( array( 'ID' => (int) $id, 'post_name' => $slug ) ); }
			$n++;
		}
		/* Clean up the extracted bundle. */
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $work, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) { $f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() ); }
		rmdir( $work );
		$done[] = $name;
		update_option( 'kz_imported_bundles', $done, false );
		kz_log( "$name: $n articles imported" );
	}
	if ( function_exists( 'wpap_internal_links_bake' ) ) { wpap_internal_links_bake( 500 ); }
}

if ( (int) get_option( 'kz_seed_version', 0 ) < KZ_SEED_VERSION ) { kz_seed_site(); }
kz_apply_ads();
kz_import_bundles();

/* Self-heal pretty permalinks on every start: a long import can leave the stored rewrite
   rules partial or emptied, and then every pretty URL (including ads.txt) 404s until a
   flush. Regenerating on every start is idempotent and cheap. */
if ( '' === get_option( 'permalink_structure', '' ) ) {
	update_option( 'permalink_structure', '/%postname%/' );
}
flush_rewrite_rules( false );
