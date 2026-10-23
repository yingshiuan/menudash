<?php
/**
 * Words on the menu page itself, in the three menu languages.
 *
 * WordPress translations follow the site's one language, so they can't switch these with
 * the guest's choice; every label is printed in all three and CSS shows the chosen one.
 *
 * A site changes any of them with the menudash_strings filter, e.g. another currency:
 *
 *   add_filter( 'menudash_strings', function ( $s ) {
 *       $s['prices'] = array( 'de' => 'Alle Preise in EUR.', 'en' => 'All prices in EUR.', 'zh' => '價格以歐元計。' );
 *       return $s;
 *   } );
 */

defined( 'ABSPATH' ) || exit;

function mdash_strings() {
	static $strings = null;
	if ( null !== $strings ) {
		return $strings;
	}
	$strings = apply_filters( 'menudash_strings', array(
		'all'        => array( 'de' => 'Alle', 'en' => 'All', 'zh' => '全部' ),
		'language'   => array( 'de' => 'Sprache', 'en' => 'Language', 'zh' => '語言' ),
		'filter'     => array( 'de' => 'Filter', 'en' => 'Filter', 'zh' => '篩選' ),
		'categories' => array( 'de' => 'Kategorien', 'en' => 'Categories', 'zh' => '分類' ),
		'pick'       => array( 'de' => 'Empfohlen', 'en' => 'Recommended', 'zh' => '推薦' ),
		'veg'        => array( 'de' => 'Vegetarisch', 'en' => 'Vegetarian', 'zh' => '素食' ),
		'vegan'      => array( 'de' => 'Vegan', 'en' => 'Vegan', 'zh' => '純素' ),
		'gf'         => array( 'de' => 'Glutenfrei', 'en' => 'Gluten-free', 'zh' => '無麩質' ),
		'lf'         => array( 'de' => 'Laktosefrei', 'en' => 'Lactose-free', 'zh' => '無乳糖' ),
		'mild'       => array( 'de' => 'Nicht scharf', 'en' => 'Not spicy', 'zh' => '不辣' ),
		'spicy'      => array( 'de' => 'Scharf', 'en' => 'Spicy', 'zh' => '辣' ),
		'vegetarian' => array( 'de' => 'Vegetarisch', 'en' => 'Vegetarian', 'zh' => '素食' ),
		'none'       => array( 'de' => 'Keine Gerichte passen zu diesen Filtern.', 'en' => 'No dishes match these filters.', 'zh' => '沒有符合篩選條件的菜式。' ),
		'reset'      => array( 'de' => 'Filter zurücksetzen', 'en' => 'Clear filters', 'zh' => '清除篩選' ),
		'show_all'   => array( 'de' => 'Alle zeigen', 'en' => 'Show all', 'zh' => '全部顯示' ),
		// While filtering: %1$s dishes shown of %2$s, then the filters' names.
		'shown'      => array( 'de' => '%1$s von %2$s Gerichten', 'en' => '%1$s of %2$s dishes', 'zh' => '%2$s 道菜中的 %1$s 道' ),
		'close'      => array( 'de' => 'Schliessen', 'en' => 'Close', 'zh' => '關閉' ),
		'photo'      => array( 'de' => 'Foto', 'en' => 'Photo', 'zh' => '照片' ),
		'prices'     => array( 'de' => 'Alle Preise in CHF inkl. MwSt.', 'en' => 'All prices in CHF incl. VAT.', 'zh' => '所有價格以瑞士法郎計，已含增值稅。' ),
		// The allergy note under the menu; %s is the phone number (Restaurant tab), and without
		// one the note ends with the staff.
		'allergy_title' => array( 'de' => 'Allergiehinweis', 'en' => 'Allergy information', 'zh' => '過敏原說明' ),
		'allergy'    => array( 'de' => 'Über Allergene und Intoleranzen in unseren Gerichten informieren Sie unsere Mitarbeitenden gerne.', 'en' => 'Our staff will be happy to tell you about allergens and intolerances in our dishes.', 'zh' => '如需了解各道菜餚所含的過敏原及不耐受成分，歡迎洽詢我們的服務人員。' ),
		'allergy_phone' => array( 'de' => 'Über Allergene und Intoleranzen in unseren Gerichten informieren Sie unsere Mitarbeitenden gerne, vor Ort oder unter %s.', 'en' => 'Our staff will be happy to tell you about allergens and intolerances in our dishes, in person or on %s.', 'zh' => '如需了解各道菜餚所含的過敏原及不耐受成分，歡迎洽詢我們的服務人員，或致電 %s。' ),
		// Where meat and fish come from (MenuDash → Menu → Meat and fish origin).
		// The jump buttons above several MenuDash boxes on one page, and the back-to-top button.
		'menu_title'   => array( 'de' => 'Speisekarte', 'en' => 'Menu', 'zh' => '菜單' ),
		'on_page'      => array( 'de' => 'Auf dieser Seite', 'en' => 'On this page', 'zh' => '本頁內容' ),
		'to_top'       => array( 'de' => 'Nach oben', 'en' => 'Back to top', 'zh' => '回到頂部' ),
		'origin_title' => array( 'de' => 'Herkunft von Fleisch und Fisch', 'en' => 'Origin of meat and fish', 'zh' => '肉類及海鮮產地' ),
		'origin_ask'   => array( 'de' => 'Fragen Sie bitte unser Personal', 'en' => 'Please ask our staff', 'zh' => '請向我們的員工查詢' ),
		// The printed QR table card (MenuDash → QR code).
		'wifi'       => array( 'de' => 'WLAN', 'en' => 'Wi-Fi', 'zh' => '無線網路' ),
		'password'   => array( 'de' => 'Passwort', 'en' => 'Password', 'zh' => '密碼' ),
		'qr_title'   => array( 'de' => 'Speisekarte scannen', 'en' => 'Scan for our menu', 'zh' => '掃描查看菜單' ),
		'qr_tip'     => array( 'de' => 'Sprache wählen und nach Vegetarisch, Vegan, Glutenfrei oder Scharf filtern.', 'en' => 'Choose your language and filter by vegetarian, vegan, gluten-free or spicy.', 'zh' => '可選擇語言，並按素食、純素、無麩質或辣度篩選。' ),
		// Recommended dishes (block menudash/picks, [menudash_picks]).
		'dish_no'    => array( 'de' => 'Nr. %s', 'en' => 'No. %s', 'zh' => '%s 號' ),
		'group_meat' => array( 'de' => 'Fleisch & Fisch', 'en' => 'Meat & fish', 'zh' => '肉類及海鮮' ),
		'group_veg'  => array( 'de' => 'Vegan & Vegetarisch', 'en' => 'Vegan & vegetarian', 'zh' => '純素及素食' ),
		'whole_menu' => array( 'de' => 'Ganze Speisekarte', 'en' => 'See the whole menu', 'zh' => '查看完整菜單' ),
		'empty'      => array( 'de' => 'Die Speisekarte wird gerade aktualisiert.', 'en' => 'The menu is being updated.', 'zh' => '菜單更新中。' ),
		// The guest's list (MenuDash → Menu → Guest list): + beside each dish, the list to show the staff.
		'list_add'   => array( 'de' => 'Zu meiner Auswahl', 'en' => 'Add to my selection', 'zh' => '加入想點的菜' ),
		'list_on'    => array( 'de' => 'In meiner Auswahl', 'en' => 'In my selection', 'zh' => '已加入想點的菜' ),
		'list_gone'  => array( 'de' => 'Aus der Auswahl genommen', 'en' => 'Removed from your selection', 'zh' => '已移除' ),
		'list_undo'  => array( 'de' => 'Rückgängig', 'en' => 'Undo', 'zh' => '復原' ),
		'list_save'  => array( 'de' => 'Speichern', 'en' => 'Save', 'zh' => '儲存' ), // short: beside "Teilen" under the picture
		'list_share' => array( 'de' => 'Mit Freunden teilen', 'en' => 'Share with friends', 'zh' => '分享給朋友' ),
		'list_send'  => array( 'de' => 'Teilen', 'en' => 'Share', 'zh' => '分享' ), // the picture with the menu's link
		// Sent with the picture and the menu's link ("Teilen" under the picture); %s: the restaurant's name.
		'list_msg'   => array( 'de' => 'Schau dir die Speisekarte von %s an:', 'en' => 'Have a look at the %s menu:', 'zh' => '來看看 %s 的菜單：' ),
		'list_hold'  => array( 'de' => 'Bild gedrückt halten, um es in den Fotos zu sichern.', 'en' => 'Press and hold the picture to save it to your photos.', 'zh' => '長按圖片即可儲存到相簿。' ),
		'list_back'  => array( 'de' => 'Zurück zur Auswahl', 'en' => 'Back to my selection', 'zh' => '返回想點的菜' ),
		'list_title' => array( 'de' => 'Meine Auswahl', 'en' => 'My selection', 'zh' => '我想點的菜' ),
		'list_tip'   => array( 'de' => 'Bitte bestellen Sie Ihre Gerichte bei unserem Servicepersonal.', 'en' => 'Please order your dishes with our service staff.', 'zh' => '請向我們的服務人員點餐。' ),
		'list_ask'   => array( 'de' => 'Ich habe eine Frage dazu', 'en' => 'I have a question about this', 'zh' => '我對此有疑問' ),
		'list_more'  => array( 'de' => 'Eins mehr', 'en' => 'One more', 'zh' => '加一份' ),
		'list_less'  => array( 'de' => 'Eins weniger', 'en' => 'One less', 'zh' => '減一份' ),
		'list_remove' => array( 'de' => 'Aus der Auswahl nehmen', 'en' => 'Remove from my selection', 'zh' => '移除' ),
		'list_total' => array( 'de' => 'Total', 'en' => 'Total', 'zh' => '合計' ),
		'list_q'     => array( 'de' => 'Frage', 'en' => 'Question', 'zh' => '有疑問' ),
		'list_price' => array( 'de' => 'Richtpreis. Der Endpreis richtet sich nach Ihrer Bestellung beim Personal.', 'en' => 'A guide only. The final price is based on your order with our staff.', 'zh' => '僅供參考，最終價格以您向服務人員點的餐為準。' ),
		'list_clear' => array( 'de' => 'Auswahl leeren', 'en' => 'Clear selection', 'zh' => '全部清空' ),
		'list_sure'  => array( 'de' => 'Die ganze Auswahl leeren?', 'en' => 'Clear your whole selection?', 'zh' => '確定全部清空？' ),
		'list_empty' => array( 'de' => 'Ihre Auswahl ist leer. Tippen Sie bei einem Gericht auf +.', 'en' => 'Your selection is empty. Tap + beside a dish.', 'zh' => '還沒有選菜。請點選菜式旁的 +。' ),
		'list_own'   => array( 'de' => 'Ihre Auswahl bleibt nur auf Ihrem Handy und wird nicht gesendet.', 'en' => 'Your selection stays on your phone and is not sent.', 'zh' => '選好的菜只保存在您的手機上，不會傳送出去。' ),
	) );
	return $strings;
}

/** Language buttons in their order on the page: code => label. German first. */
function mdash_lang_buttons() {
	return array( 'de' => 'DE', 'en' => 'EN', 'zh' => '中文' );
}

/** HTML lang attribute per menu language; the Chinese on this menu is Traditional. */
function mdash_html_lang( $l ) {
	$map = array( 'en' => 'en', 'de' => 'de', 'zh' => 'zh-Hant' );
	return $map[ $l ];
}
