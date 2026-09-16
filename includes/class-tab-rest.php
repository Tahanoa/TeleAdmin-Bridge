<?php
defined( 'ABSPATH' ) || exit;

final class TAB_REST {
	public static function boot() { add_action( 'rest_api_init', array( __CLASS__, 'routes' ) ); }
	public static function routes() { register_rest_route( 'teleadmin/v1', '/webhook/(?P<secret>[A-Za-z0-9]+)', array( 'methods'=>'POST', 'callback'=>array(__CLASS__,'webhook'), 'permission_callback'=>array(__CLASS__,'authorize') ) ); }
	public static function authorize( WP_REST_Request $r ) { $s=TAB_Plugin::settings(); $h=(string)$r->get_header('X-Telegram-Bot-Api-Secret-Token'); return !empty($s['secret'])&&hash_equals($s['secret'],(string)$r['secret'])&&hash_equals($s['secret'],$h); }
	public static function webhook( WP_REST_Request $r ) { $u=$r->get_json_params(); if(!is_array($u)||empty($u['update_id']))return new WP_REST_Response(array('ok'=>false),400); $k='tab_update_'.absint($u['update_id']); if(get_transient($k))return array('ok'=>true); set_transient($k,1,DAY_IN_SECONDS); self::handle($u); return array('ok'=>true); }

	private static function handle( $u ) {
		$m=$u['message']??array(); $chat=isset($m['chat']['id'])?(string)$m['chat']['id']:''; $text=isset($m['text'])?trim(sanitize_text_field($m['text'])):''; if(!$chat||!$text)return;
		if(0===strpos($text,'/start connect_')){self::connect($chat,substr($text,15),$m);return;}
		$user=self::authorized_user($chat); if(!$user){TAB_Telegram::send($chat,'⛔ Access denied. Connect from WordPress → TeleAdmin Bridge.');return;}
		$lang=get_user_meta($user->ID,'tab_language',true)?:TAB_Plugin::settings()['language'];
		if(in_array($text,array('/cancel','لغو','❌ لغو','❌ Cancel'),true)){self::clear($user->ID);TAB_Telegram::send($chat,self::t($lang,'cancelled'),TAB_Telegram::menu($lang));return;}
		if(false!==strpos($text,'English')){self::clear($user->ID);update_user_meta($user->ID,'tab_language','en');TAB_Telegram::send($chat,'Language changed.',TAB_Telegram::menu('en'));return;}
		if(false!==strpos($text,'فارسی')){self::clear($user->ID);update_user_meta($user->ID,'tab_language','fa');TAB_Telegram::send($chat,'زبان تغییر کرد.',TAB_Telegram::menu('fa'));return;}
		$f=get_user_meta($user->ID,'tab_flow',true); if(is_array($f)){self::flow($user,$chat,$text,$lang,$f);return;}
		if(false!==strpos($text,'وضعیت')||false!==stripos($text,'status')){self::status($chat,$lang);return;}
		if(false!==strpos($text,'سفارش')||false!==stripos($text,'orders')){if(!function_exists('wc_get_orders')){self::error($chat,$lang,'wc_missing');return;}self::orders($chat,$lang);return;}
		if(false!==strpos($text,'صورتحساب جدید')||false!==stripos($text,'new invoice')){if(!class_exists('EZINV_DB')){self::error($chat,$lang,'invoice_missing');return;}self::save($user->ID,'invoice','title',array());TAB_Telegram::send($chat,self::t($lang,'invoice_title'),self::cancel_kb($lang));return;}
		if(false!==strpos($text,'محصول جدید')||false!==stripos($text,'new product')){if(!class_exists('WC_Product_Simple')){self::error($chat,$lang,'wc_missing');return;}self::save($user->ID,'product','name',array());TAB_Telegram::send($chat,self::t($lang,'product_name'),self::cancel_kb($lang));return;}
		TAB_Telegram::send($chat,self::t($lang,'choose'),TAB_Telegram::menu($lang));
	}

	private static function flow($user,$chat,$text,$lang,$f){
		if('product'===$f['type']){if(!class_exists('WC_Product_Simple')){self::clear($user->ID);self::error($chat,$lang,'wc_missing');return;}self::product($user,$chat,$text,$lang,$f);return;}
		if('invoice'===$f['type']){if(!class_exists('EZINV_DB')){self::clear($user->ID);self::error($chat,$lang,'invoice_missing');return;}self::invoice($user,$chat,$text,$lang,$f);}
	}
	private static function product($user,$chat,$text,$lang,$f){
		$d=$f['data'];$s=$f['step'];
		if('name'===$s){if(mb_strlen($text)<2){self::retry($chat,$lang,'invalid_name');return;}$d['name']=sanitize_text_field($text);self::next($user,'product','price',$d,$chat,$lang,'product_price');return;}
		if('price'===$s){$v=self::num($text);if($v<=0){self::retry($chat,$lang,'invalid_price');return;}$d['price']=$v;self::next($user,'product','sale',$d,$chat,$lang,'product_sale',true);return;}
		if('sale'===$s){$v=self::skip($text)?'':self::num($text);if(''!==$v&&($v<=0||$v>=$d['price'])){self::retry($chat,$lang,'invalid_sale');return;}$d['sale']=$v;self::next($user,'product','stock',$d,$chat,$lang,'product_stock',true);return;}
		if('stock'===$s){$v=self::skip($text)?'':self::num($text);$d['stock']=$v;self::next($user,'product','sku',$d,$chat,$lang,'product_sku',true);return;}
		if('sku'===$s){$v=self::skip($text)?'':wc_clean($text);if($v&&wc_get_product_id_by_sku($v)){self::retry($chat,$lang,'duplicate_sku');return;}$d['sku']=$v;self::next($user,'product','description',$d,$chat,$lang,'product_description',true);return;}
		if('description'===$s){$d['description']=self::skip($text)?'':sanitize_textarea_field($text);self::save($user->ID,'product','confirm',$d);TAB_Telegram::send($chat,self::product_summary($d,$lang),self::confirm_kb($lang));return;}
		if('confirm'===$s){if(!self::confirm($text)){TAB_Telegram::send($chat,self::t($lang,'confirm_hint'),self::confirm_kb($lang));return;}$p=new WC_Product_Simple();$p->set_name($d['name']);$p->set_regular_price(wc_format_decimal($d['price']));if(''!==$d['sale'])$p->set_sale_price(wc_format_decimal($d['sale']));if(''!==$d['stock']){$p->set_manage_stock(true);$p->set_stock_quantity(absint($d['stock']));}if($d['sku'])$p->set_sku($d['sku']);if($d['description'])$p->set_description($d['description']);$p->set_status('draft');$id=$p->save();self::clear($user->ID);TAB_Telegram::send($chat,sprintf(self::t($lang,'product_done'),$id),TAB_Telegram::menu($lang));}
	}
	private static function invoice($user,$chat,$text,$lang,$f){
		$d=$f['data'];$s=$f['step'];
		if('title'===$s){if(mb_strlen($text)<2){self::retry($chat,$lang,'invalid_name');return;}$d['title']=sanitize_text_field($text);self::next($user,'invoice','amount',$d,$chat,$lang,'invoice_amount');return;}
		if('amount'===$s){$v=self::num($text);if($v<=0){self::retry($chat,$lang,'invalid_price');return;}$d['amount']=$v;self::next($user,'invoice','customer',$d,$chat,$lang,'invoice_customer',true);return;}
		if('customer'===$s){$d['customer']=self::skip($text)?'':sanitize_text_field($text);self::next($user,'invoice','mobile',$d,$chat,$lang,'invoice_mobile',true);return;}
		if('mobile'===$s){$v=self::skip($text)?'':preg_replace('/[^0-9+]/','',$text);if($v&&!preg_match('/^\+?[0-9]{10,15}$/',$v)){self::retry($chat,$lang,'invalid_mobile');return;}$d['mobile']=$v;self::next($user,'invoice','description',$d,$chat,$lang,'invoice_description',true);return;}
		if('description'===$s){$d['description']=self::skip($text)?'':sanitize_textarea_field($text);self::save($user->ID,'invoice','confirm',$d);TAB_Telegram::send($chat,self::invoice_summary($d,$lang),self::confirm_kb($lang));return;}
		if('confirm'===$s){if(!self::confirm($text)){TAB_Telegram::send($chat,self::t($lang,'confirm_hint'),self::confirm_kb($lang));return;}$token=bin2hex(random_bytes(24));$now=current_time('mysql');$id=EZINV_DB::insert_invoice(array('token'=>$token,'title'=>$d['title'],'customer_name'=>$d['customer'],'mobile'=>$d['mobile'],'email'=>'','items_json'=>null,'subtotal_irr'=>$d['amount']*10,'shipping_irr'=>0,'amount_irr'=>$d['amount']*10,'description'=>$d['description'],'status'=>'pending','created_at'=>$now,'updated_at'=>$now));if(is_wp_error($id)){TAB_Telegram::send($chat,self::t($lang,'invoice_save_error').' '.$id->get_error_message());return;}$url=class_exists('EZINV_Invoice')?EZINV_Invoice::invoice_url($token):home_url('/?ezinv_invoice='.rawurlencode($token));self::clear($user->ID);TAB_Telegram::send($chat,sprintf(self::t($lang,'invoice_done'),$id)."\n".esc_url_raw($url),TAB_Telegram::menu($lang));}
	}

	private static function connect($chat,$code,$m){$r=get_transient('tab_pair_'.sanitize_key($code));if(!is_array($r)||empty($r['user_id'])||!user_can($r['user_id'],'manage_options')){TAB_Telegram::send($chat,'Invalid or expired connection code.');return;}update_user_meta($r['user_id'],'tab_telegram_chat_id',$chat);update_user_meta($r['user_id'],'tab_telegram_username',sanitize_text_field($m['from']['username']??''));delete_transient('tab_pair_'.sanitize_key($code));TAB_Telegram::send($chat,'✅ Connected to '.wp_specialchars_decode(get_bloginfo('name')),TAB_Telegram::menu(TAB_Plugin::settings()['language']));}
	private static function authorized_user($chat){$u=get_users(array('meta_key'=>'tab_telegram_chat_id','meta_value'=>$chat,'number'=>1));return !empty($u)&&user_can($u[0]->ID,'manage_options')?$u[0]:false;}
	private static function status($chat,$lang){$t=self::t($lang,'online')."\nWordPress: ".get_bloginfo('version')."\nWooCommerce: ".(class_exists('WooCommerce')?'ON':'OFF')."\nTahanoa Invoice: ".(class_exists('EZINV_DB')?'ON':'OFF');TAB_Telegram::send($chat,$t);}
	private static function orders($chat,$lang){$a=array(self::t($lang,'recent_orders'));foreach(wc_get_orders(array('limit'=>5,'orderby'=>'date','order'=>'DESC'))as$o)$a[]='#'.$o->get_id().' — '.wp_strip_all_tags($o->get_formatted_order_total()).' — '.wc_get_order_status_name($o->get_status());TAB_Telegram::send($chat,implode("\n",$a));}
	private static function next($u,$type,$step,$d,$chat,$lang,$key,$skip=false){self::save($u->ID,$type,$step,$d);TAB_Telegram::send($chat,self::t($lang,$key),$skip?self::skip_kb($lang):self::cancel_kb($lang));}
	private static function save($id,$type,$step,$data){update_user_meta($id,'tab_flow',array('type'=>$type,'step'=>$step,'data'=>$data,'updated'=>time()));}
	private static function clear($id){delete_user_meta($id,'tab_flow');}
	private static function num($v){$v=strtr($v,array('۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٬'=>'',','=>''));return absint(preg_replace('/[^0-9]/','',$v));}
	private static function skip($t){return in_array($t,array('⏭ رد شدن','⏭ Skip','-'),true);}
	private static function confirm($t){return in_array($t,array('✅ تأیید و ثبت','✅ Confirm & create'),true);}
	private static function cancel_kb($l){return array('keyboard'=>array(array(array('text'=>'fa'===$l?'❌ لغو':'❌ Cancel'))),'resize_keyboard'=>true);}
	private static function skip_kb($l){return array('keyboard'=>array(array(array('text'=>'fa'===$l?'⏭ رد شدن':'⏭ Skip')),array(array('text'=>'fa'===$l?'❌ لغو':'❌ Cancel'))),'resize_keyboard'=>true);}
	private static function confirm_kb($l){return array('keyboard'=>array(array(array('text'=>'fa'===$l?'✅ تأیید و ثبت':'✅ Confirm & create')),array(array('text'=>'fa'===$l?'❌ لغو':'❌ Cancel'))),'resize_keyboard'=>true);}
	private static function retry($c,$l,$k){TAB_Telegram::send($c,'⚠️ '.self::t($l,$k));}
	private static function error($c,$l,$k){TAB_Telegram::send($c,'⚠️ '.self::t($l,$k),TAB_Telegram::menu($l));}
	private static function product_summary($d,$l){return self::t($l,'review')."\n\n".self::t($l,'name').': '.$d['name']."\n".self::t($l,'price').': '.number_format($d['price'])."\n".self::t($l,'sale').': '.(''===$d['sale']?'—':number_format($d['sale']))."\n".self::t($l,'stock').': '.(''===$d['stock']?'—':$d['stock'])."\nSKU: ".($d['sku']?:'—')."\n".self::t($l,'status').': Draft';}
	private static function invoice_summary($d,$l){return self::t($l,'review')."\n\n".self::t($l,'title').': '.$d['title']."\n".self::t($l,'amount').': '.number_format($d['amount'])."\n".self::t($l,'customer').': '.($d['customer']?:'—')."\n".self::t($l,'mobile').': '.($d['mobile']?:'—');}
	private static function t($l,$k){$x=array(
	'choose'=>array('یک گزینه را انتخاب کنید.','Choose an option.'),'cancelled'=>array('فرایند لغو شد.','Process cancelled.'),'wc_missing'=>array('ووکامرس فعال نیست؛ امکان مشاهده سفارش یا ساخت محصول وجود ندارد.','WooCommerce is not active; orders and product creation are unavailable.'),'invoice_missing'=>array('افزونه صورتحساب Tahanoa فعال نیست؛ امکان ساخت صورتحساب وجود ندارد.','Tahanoa Invoice Links is not active; invoice creation is unavailable.'),'online'=>array('✅ سایت فعال است','✅ Site is online'),'recent_orders'=>array('📦 سفارش‌های اخیر','📦 Recent orders'),
	'product_name'=>array('نام محصول را ارسال کنید.','Send the product name.'),'product_price'=>array('قیمت اصلی محصول را وارد کنید.','Enter the regular product price.'),'product_sale'=>array('قیمت فروش ویژه را وارد کنید یا «رد شدن» را بزنید.','Enter a sale price or tap Skip.'),'product_stock'=>array('تعداد موجودی را وارد کنید یا «رد شدن» را بزنید.','Enter stock quantity or tap Skip.'),'product_sku'=>array('شناسه SKU را وارد کنید یا «رد شدن» را بزنید.','Enter an SKU or tap Skip.'),'product_description'=>array('توضیحات محصول را وارد کنید یا «رد شدن» را بزنید.','Enter a description or tap Skip.'),'product_done'=>array('✅ محصول #%d به‌صورت پیش‌نویس ساخته شد.','✅ Product #%d was created as a draft.'),
	'invoice_title'=>array('عنوان صورتحساب را ارسال کنید.','Send the invoice title.'),'invoice_amount'=>array('مبلغ صورتحساب را به تومان وارد کنید.','Enter the invoice amount in Toman.'),'invoice_customer'=>array('نام مشتری را وارد کنید یا «رد شدن» را بزنید.','Enter the customer name or tap Skip.'),'invoice_mobile'=>array('شماره موبایل را وارد کنید یا «رد شدن» را بزنید.','Enter the mobile number or tap Skip.'),'invoice_description'=>array('توضیحات را وارد کنید یا «رد شدن» را بزنید.','Enter a description or tap Skip.'),'invoice_done'=>array('✅ صورتحساب #%d ساخته شد.','✅ Invoice #%d was created.'),'invoice_save_error'=>array('ذخیره صورتحساب ناموفق بود.','Could not save the invoice.'),
	'invalid_name'=>array('نام یا عنوان معتبر نیست؛ حداقل دو حرف وارد کنید.','Invalid name or title; enter at least two characters.'),'invalid_price'=>array('مبلغ معتبر و بزرگ‌تر از صفر وارد کنید.','Enter a valid amount greater than zero.'),'invalid_sale'=>array('قیمت ویژه باید از قیمت اصلی کمتر باشد.','Sale price must be lower than regular price.'),'duplicate_sku'=>array('این SKU قبلاً استفاده شده است.','This SKU is already in use.'),'invalid_mobile'=>array('شماره موبایل معتبر نیست؛ ۱۰ تا ۱۵ رقم وارد کنید.','Invalid mobile number; enter 10 to 15 digits.'),'confirm_hint'=>array('برای ثبت، دکمه تأیید را بزنید یا لغو کنید.','Tap Confirm to create it, or cancel.'),'review'=>array('🔎 لطفاً اطلاعات را بررسی کنید:','🔎 Please review the information:'),
	'name'=>array('نام','Name'),'price'=>array('قیمت اصلی','Regular price'),'sale'=>array('قیمت ویژه','Sale price'),'stock'=>array('موجودی','Stock'),'status'=>array('وضعیت','Status'),'title'=>array('عنوان','Title'),'amount'=>array('مبلغ تومان','Amount (Toman)'),'customer'=>array('مشتری','Customer'),'mobile'=>array('موبایل','Mobile'));return $x[$k]['fa'===$l?0:1]??$k;}
}
