<?php
/**
 * GMCQ Admin Bootstrap
 * Stage 3.8 — Registers admin menus, dashboard page, and capability checks.
 */
defined( 'ABSPATH' ) || exit;

function gmcq_register_admin_menus(): void {
	add_menu_page(__( 'GMCQ Dashboard', 'gmcq' ),__( 'GMCQ', 'gmcq' ),'manage_gmcq','gmcq-dashboard','gmcq_render_dashboard_page','dashicons-analytics',30);
	add_submenu_page('gmcq-dashboard',__( 'GMCQ Dashboard', 'gmcq' ),__( 'Dashboard', 'gmcq' ),'manage_gmcq','gmcq-dashboard','gmcq_render_dashboard_page');
	add_submenu_page('gmcq-dashboard',__( 'Categories', 'gmcq' ),__( 'Categories', 'gmcq' ),'manage_gmcq','gmcq-categories','gmcq_render_categories_page');
add_submenu_page('gmcq-dashboard',__( 'Questions', 'gmcq' ),__( 'Questions', 'gmcq' ),'manage_gmcq','gmcq-questions','gmcq_render_questions_page');
	add_submenu_page('gmcq-dashboard',__( 'Quizzes', 'gmcq' ),__( 'Quizzes', 'gmcq' ),'manage_gmcq','gmcq-quizzes','gmcq_render_quizzes_page');
	add_submenu_page('gmcq-dashboard',__( 'CSV Import', 'gmcq' ),__( 'CSV Import', 'gmcq' ),'manage_gmcq','gmcq-import','gmcq_render_import_page');
	add_submenu_page('gmcq-dashboard',__( 'Reports', 'gmcq' ),__( 'Reports', 'gmcq' ),'manage_gmcq','gmcq-reports','gmcq_render_reports_page');
	add_submenu_page('gmcq-dashboard',__( 'Settings', 'gmcq' ),__( 'Settings', 'gmcq' ),'manage_gmcq','gmcq-settings','gmcq_render_settings_page');
}
add_action('admin_menu','gmcq_register_admin_menus');

function gmcq_admin_enqueue_scripts(string $hook):void{
	if(strpos($hook,'gmcq-')===false&&$hook!=='toplevel_page_gmcq-dashboard')return;
	$u=wp_upload_dir();$c=$u['baseurl'].'/gmcq-assets/css/admin.css';
	wp_enqueue_style('gmcq-admin',$c,array(),GMCQ_VERSION);
	wp_localize_script('jquery','gmcqAdmin',array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('gmcq_category_nonce'),'version'=>GMCQ_VERSION));
}
add_action('admin_enqueue_scripts','gmcq_admin_enqueue_scripts');

function gmcq_create_assets_dir():void{
	$d=wp_upload_dir()['basedir'].'/gmcq-assets';
	if(!file_exists($d))wp_mkdir_p($d);
	$cd=$d.'/css';
	if(!file_exists($cd)){
		wp_mkdir_p($cd);
		$c='/* GMCQ Admin Styles */'.PHP_EOL.'.gmcq-dashboard-wrap{padding:20px 0}'.PHP_EOL.'.gmcq-card{background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px;margin:10px 0}'.PHP_EOL.'.gmcq-card h2{margin-top:0}'.PHP_EOL.'.gmcq-status-ok{color:#46b450;font-weight:600}'.PHP_EOL.'.gmcq-status-warning{color:#ffb900;font-weight:600}'.PHP_EOL.'.gmcq-status-inactive{color:#dc3232;font-weight:600}'.PHP_EOL.'.gmcq-filter-tabs{margin:15px 0}'.PHP_EOL.'.gmcq-filter-tabs a{display:inline-block;padding:6px 16px;text-decoration:none;border:1px solid #ccd0d4;border-radius:3px 3px 0 0;background:#f1f1f1;margin-right:4px}'.PHP_EOL.'.gmcq-filter-tabs a.current{background:#fff;border-bottom-color:#fff;font-weight:600}'.PHP_EOL.'.gmcq-search-box{margin:15px 0}'.PHP_EOL;
		@file_put_contents($cd.'/admin.css',$c);
	}
}
add_action('admin_init','gmcq_create_assets_dir');

function gmcq_render_dashboard_page(): void {
	if ( function_exists( 'gmcq_render_full_dashboard_page' ) ) {
		gmcq_render_full_dashboard_page();
		return;
	}
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}
}

function gmcq_render_categories_page():void{
	if(!current_user_can('manage_gmcq'))wp_die(esc_html__('You do not have permission to access this page.','gmcq'));
	$a=isset($_GET['action'])?sanitize_key($_GET['action']):'';
	if('add'===$a){gmcq_render_category_add_form();return;}
	elseif('edit'===$a&&isset($_GET['id'])){gmcq_render_category_edit_form((int)$_GET['id']);return;}
	$cf=isset($_GET['filter'])?sanitize_key($_GET['filter']):'all';
	$cs=isset($_GET['s'])?sanitize_text_field($_GET['s']):'';
	$tree=gmcq_get_category_tree(array('filter'=>$cf,'search'=>$cs));
	$cats=array();foreach($tree as$p){$cats[]=$p;if(!empty($p->children)){foreach($p->children as$c){$cats[]=$c;}}}
	$hc=!empty($cats);$bu=admin_url('admin.php?page=gmcq-categories');
	?><div class="wrap gmcq-dashboard-wrap"><h1><?php printf('<a href="%s">%s</a> &rsaquo; %s',esc_url(admin_url('admin.php?page=gmcq-dashboard')),esc_html__('GMCQ','gmcq'),esc_html__('Categories','gmcq'));?></h1>
	<div class="gmcq-card"><h2><?php esc_html_e('Category Management','gmcq');?></h2>
	<p><?php printf(esc_html__('Manage exam categories for organizing questions. %s to get started.','gmcq'),'<a href="'.esc_url($bu.'&action=add').'">'.esc_html__('Add New Category','gmcq').'</a>');?></p>
	<p class="description"><?php esc_html_e('Categories support 2-level hierarchy: Parent → Child. No deeper nesting.','gmcq');?></p>
	<div class="gmcq-filter-tabs"><a href="<?php echo esc_url($bu.($cs?'&s='.urlencode($cs):''));?>" class="<?php echo 'all'===$cf?'current':'';?>"><?php esc_html_e('All','gmcq');?></a>
	<a href="<?php echo esc_url($bu.'&filter=active'.($cs?'&s='.urlencode($cs):''));?>" class="<?php echo 'active'===$cf?'current':'';?>"><?php esc_html_e('Active','gmcq');?></a>
	<a href="<?php echo esc_url($bu.'&filter=inactive'.($cs?'&s='.urlencode($cs):''));?>" class="<?php echo 'inactive'===$cf?'current':'';?>"><?php esc_html_e('Inactive','gmcq');?></a></div>
	<div class="gmcq-search-box"><form method="get" action=""><input type="hidden" name="page" value="gmcq-categories"><input type="hidden" name="filter" value="<?php echo esc_attr($cf);?>">
	<label for="gmcq-cat-search" class="screen-reader-text"><?php esc_html_e('Search categories','gmcq');?></label>
	<input type="text" id="gmcq-cat-search" name="s" value="<?php echo esc_attr($cs);?>" placeholder="<?php esc_attr_e('Search by name or slug...','gmcq');?>" style="width:250px">
	<button type="submit" class="button"><?php esc_html_e('Search','gmcq');?></button>
	<?php if($cs):?><a href="<?php echo esc_url($bu.($cf!=='all'?'&filter='.$cf:''));?>" class="button"><?php esc_html_e('Clear','gmcq');?></a><?php endif;?></form></div>
	<?php if($hc):?>
	<table class="wp-list-table widefat fixed striped" style="max-width:900px;margin-top:15px"><thead><tr>
	<th style="width:40px"><?php esc_html_e('ID','gmcq');?></th><th style="width:220px"><?php esc_html_e('Name','gmcq');?></th>
	<th style="width:150px"><?php esc_html_e('Slug','gmcq');?></th><th style="width:60px"><?php esc_html_e('Questions','gmcq');?></th>
	<th style="width:80px"><?php esc_html_e('Status','gmcq');?></th></tr></thead>
	<tbody id="gmcq-categories-tbody"><?php foreach($cats as$cat):?>
	<tr><td><?php echo esc_html($cat->id);?></td>
	<td><?php $indent=!empty($cat->parent_id)?'<span class="gmcq-indent" style="color:#999;margin-right:8px;font-weight:normal;">&mdash;</span> ':'';echo $indent.'<strong>'.esc_html($cat->name).'</strong>';?>
	<div class="row-actions" style="font-size:13px">
	<span class="edit"><a href="<?php echo esc_url(admin_url('admin.php?page=gmcq-categories&action=edit&id='.$cat->id));?>"><?php esc_html_e('Edit','gmcq');?></a></span>
	<?php if(1===(int)$cat->is_active):?>
	<span class="deactivate"> | <a href="#" class="gmcq-deactivate-cat" data-id="<?php echo esc_attr($cat->id);?>" data-name="<?php echo esc_attr($cat->name);?>" style="color:#dc3232"><?php esc_html_e('Deactivate','gmcq');?></a></span>
	<?php else:?>
	<span class="activate"> | <a href="#" class="gmcq-activate-cat" data-id="<?php echo esc_attr($cat->id);?>" data-name="<?php echo esc_attr($cat->name);?>" style="color:#46b450"><?php esc_html_e('Reactivate','gmcq');?></a></span>
	<span class="trash"> | <a href="#" class="gmcq-delete-cat" data-id="<?php echo esc_attr($cat->id);?>" data-name="<?php echo esc_attr($cat->name);?>" data-questions="<?php echo esc_attr($cat->question_count);?>" style="color:#a00"><?php esc_html_e('Remove','gmcq');?></a></span>
	<?php endif;?></div></td>
	<td><code><?php echo esc_html($cat->slug);?></code></td>
	<td><?php echo esc_html($cat->question_count);?></td>
	<td><?php if(1===(int)$cat->is_active):?><span class="gmcq-status-ok"><?php esc_html_e('Active','gmcq');?></span><?php else:?><span class="gmcq-status-inactive"><?php esc_html_e('Inactive','gmcq');?></span><?php endif;?></td>
	</tr><?php endforeach;?></tbody></table>
	<?php else:?><div class="gmcq-card" style="text-align:center;padding:40px"><p style="font-size:16px;color:#666"><?php esc_html_e('No categories found.','gmcq');?></p></div><?php endif;?></div></div>
	<div id="gmcq-notice-area" role="alert" aria-live="polite" style="display:none;position:fixed;top:50px;right:20px;z-index:10000;max-width:400px;padding:12px 20px;border-left:4px solid #46b450;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.15)"></div>
	<script>
	jQuery(document).ready(function($){
		var $tb=$('#gmcq-categories-tbody'),$n=$('#gmcq-notice-area'),nonce=gmcqAdmin.nonce;
		function sn(m,e){$n.css('border-color',e?'#dc3232':'#46b450').text(m).fadeIn(300).delay(e?5000:2000).fadeOut(600);}
		$tb.on('click','a.gmcq-deactivate-cat,a.gmcq-activate-cat,a.gmcq-delete-cat',function(e){
			e.preventDefault();var $l=$(this),id=parseInt($l.data('id')),name=$l.data('name')||'';
			if($l.hasClass('gmcq-deactivate-cat')){
				if(!confirm('<?php echo esc_js(__('Are you sure you want to deactivate this category?','gmcq'));?>\n\n'+name))return;
				$l.css('opacity',0.5);$.post(gmcqAdmin.ajaxUrl,{action:'gmcq_deactivate_category',id:id,_ajax_nonce:nonce},function(r){
					if(r.success){sn(name+': '+r.data.message);setTimeout(function(){window.location.reload();},800);}else{sn(r.data.message||'<?php echo esc_js(__('Error deactivating category.','gmcq'));?>',true);$l.css('opacity',1);}
				}).fail(function(){sn('<?php echo esc_js(__('Server error.','gmcq'));?>',true);$l.css('opacity',1);});
			}else if($l.hasClass('gmcq-activate-cat')){
				if(!confirm('<?php echo esc_js(__('Activate this category?','gmcq'));?>\n\n'+name))return;
				$l.css('opacity',0.5);$.post(gmcqAdmin.ajaxUrl,{action:'gmcq_activate_category',id:id,_ajax_nonce:nonce},function(r){
					if(r.success){sn(name+': '+r.data.message);setTimeout(function(){window.location.reload();},800);}else{sn(r.data.message||'<?php echo esc_js(__('Error activating category.','gmcq'));?>',true);$l.css('opacity',1);}
				}).fail(function(){sn('<?php echo esc_js(__('Server error.','gmcq'));?>',true);$l.css('opacity',1);});
			}else if($l.hasClass('gmcq-delete-cat')){
				var q=parseInt($l.data('questions'))||0,msg='<?php echo esc_js(__('This will permanently deactivate this category. The row remains in the database for historical reference (Phase 1 spec).','gmcq'));?>\n\n'+name;
				if(q>0)msg+='\n\n<?php echo esc_js(__('Warning: This category has','gmcq'));?> '+q+' <?php echo esc_js(__('question(s).','gmcq'));?>';
				msg+='\n\n<?php echo esc_js(__('Continue?','gmcq'));?>';if(!confirm(msg))return;
				$l.css('opacity',0.5);$.post(gmcqAdmin.ajaxUrl,{action:'gmcq_delete_category',id:id,_ajax_nonce:nonce},function(r){
					if(r.success){sn(name+': '+r.data.message);setTimeout(function(){window.location.reload();},800);}else{sn(r.data.message||'<?php echo esc_js(__('Error removing category.','gmcq'));?>',true);$l.css('opacity',1);}
				}).fail(function(){sn('<?php echo esc_js(__('Server error.','gmcq'));?>',true);$l.css('opacity',1);});
			}
		});
	});
	</script><?php
}

function gmcq_render_category_add_form():void{
	$mr=gmcq_get_categories(array('parent_only'=>true,'filter'=>'active'));$mains=$mr['categories'];$hm=!empty($mains);
	$au=admin_url('admin-ajax.php');$n=wp_create_nonce('gmcq_category_nonce');$lu=admin_url('admin.php?page=gmcq-categories');
	?><div class="wrap gmcq-dashboard-wrap"><h1><?php esc_html_e('Add New Category','gmcq');?></h1>
	<div class="gmcq-card" style="max-width:800px">
	<form id="gmcq-add-category-form"><input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($n);?>">
	<fieldset><legend style="font-weight:bold;font-size:14px;margin-bottom:10px"><?php esc_html_e('Main Category','gmcq');?></legend>
	<p class="description" style="margin-bottom:8px"><?php esc_html_e('Select an existing main category or type a new one.','gmcq');?></p>
	<div id="gmcq-main-field"><?php if($hm):?>
	<label for="gmcq-main-select"><?php esc_html_e('Existing Main Category','gmcq');?></label><br>
	<select name="main_category" id="gmcq-main-select" class="regular-text" aria-describedby="gmcq-main-desc">
	<option value=""><?php esc_html_e('— Select or type new —','gmcq');?></option>
	<?php foreach($mains as$main):?><option value="<?php echo esc_attr($main->id);?>"><?php echo esc_html($main->name);?></option><?php endforeach;?>
	<option value="__other__"><?php esc_html_e('Other (Type New)','gmcq');?></option></select><?php endif;?>
	<div id="gmcq-main-new-wrapper" style="<?php echo $hm?'display:none;':'';?>margin-top:8px"><label for="gmcq-main-new"><?php esc_html_e('New Main Category Name','gmcq');?> <span style="color:red">*</span></label><br>
	<input name="main_category_new" type="text" id="gmcq-main-new" class="regular-text" placeholder="<?php esc_attr_e('Type new main category name...','gmcq');?>" <?php echo $hm?'':'required';?>></div>
	<p id="gmcq-main-desc" class="description" style="margin-top:4px"><?php esc_html_e('A unique slug will be auto-generated from the name.','gmcq');?></p></div></fieldset>
	<fieldset id="gmcq-sub-fieldset" style="display:none;margin-top:20px"><legend style="font-weight:bold;font-size:14px;margin-bottom:10px"><?php esc_html_e('Sub Category (Optional)','gmcq');?></legend>
	<p class="description" style="margin-bottom:8px"><?php esc_html_e('Select an existing subcategory or type a new one under the selected main category.','gmcq');?></p>
	<div id="gmcq-sub-field">
	<div id="gmcq-sub-select-wrapper"><label for="gmcq-sub-select"><?php esc_html_e('Existing Subcategory','gmcq');?></label><br>
	<select name="sub_category" id="gmcq-sub-select" class="regular-text"><option value=""><?php esc_html_e('— None —','gmcq');?></option></select></div>
	<div id="gmcq-sub-new-wrapper" style="margin-top:8px"><label for="gmcq-sub-new"><?php esc_html_e('New Subcategory Name','gmcq');?></label><br>
	<input name="sub_category_new" type="text" id="gmcq-sub-new" class="regular-text" placeholder="<?php esc_attr_e('Type new subcategory name...','gmcq');?>"></div>
	</div></fieldset>
	<fieldset style="margin-top:20px"><legend style="font-weight:bold;font-size:14px;margin-bottom:10px"><?php esc_html_e('Description (Optional)','gmcq');?></legend>
	<label for="gmcq-cat-desc" class="screen-reader-text"><?php esc_html_e('Description','gmcq');?></label>
	<textarea name="description" id="gmcq-cat-desc" rows="4" class="large-text" style="width:100%"></textarea></fieldset>
	<p class="submit" style="margin-top:20px"><button type="submit" class="button button-primary"><?php esc_html_e('Save Category','gmcq');?></button>
	<a href="<?php echo esc_url($lu);?>" class="button button-secondary"><?php esc_html_e('Cancel','gmcq');?></a></p></form>
	<div id="gmcq-form-response" role="alert" aria-live="polite" style="margin-top:15px;padding:10px;display:none;border-left:4px solid transparent"></div></div></div>
	<script>
	jQuery(document).ready(function($){
		var hm=<?php echo $hm?'true':'false';?>,$ms=$('#gmcq-main-select'),$mn=$('#gmcq-main-new'),$mnw=$('#gmcq-main-new-wrapper'),$sf=$('#gmcq-sub-fieldset'),$ss=$('#gmcq-sub-select'),$ssw=$('#gmcq-sub-select-wrapper'),$sn=$('#gmcq-sub-new'),$snw=$('#gmcq-sub-new-wrapper'),$f=$('#gmcq-add-category-form'),$r=$('#gmcq-form-response'),ajax='<?php echo esc_url($au);?>',nonce='<?php echo esc_js($n);?>',list='<?php echo esc_url($lu);?>';
		function hmc(){
			var v=$ms.val();
			if(v==='__other__'){$mnw.slideDown(200);$mn.prop('required',true).focus();}else{$mnw.slideUp(200);$mn.prop('required',false);}
			var mid=(v&&v!==''&&v!=='__other__')?v:($mn.val().trim()?'new':'');
			if(mid){
				$sf.slideDown(200);
				if(v&&v!==''&&v!=='__other__'){$ssw.show();$snw.hide();ls(parseInt(v));}
				else{$ssw.hide();$snw.show();}
			}else{$sf.slideUp(200);}
		}
		function ls(p){
			$ss.prop('disabled',true).html('<option><?php echo esc_js(__('Loading...','gmcq'));?></option>');
			$.get(ajax,{action:'gmcq_get_subcategories',parent_id:p,_ajax_nonce:nonce},function(r){
				$ss.prop('disabled',false).empty().append('<option value=""><?php echo esc_js(__('— None —','gmcq'));?></option>');
				if(r.success){
					if(r.data.subcategories&&r.data.subcategories.length){
						$.each(r.data.subcategories,function(i,s){$ss.append('<option value="'+s.id+'">'+$('<span>').text(s.name).html()+'</option>');});
					}
					$ss.append('<option value="__other__"><?php echo esc_js(__('Other (Type New)','gmcq'));?></option>');
				}
			}).fail(function(){$ss.prop('disabled',false).empty().append('<option value=""><?php echo esc_js(__('— None —','gmcq'));?></option>');});
		}
		function hsc(){($ss.val()==='__other__')?$snw.slideDown(200):$snw.slideUp(200);}
		if(hm)$ms.on('change',hmc);
		$mn.on('input',function(){$(this).val().trim()?($sf.slideDown(200),$ssw.hide(),$snw.show()):(!$ms.val()||$ms.val()==='')&&$sf.slideUp(200);});
		$ss.on('change',hsc);
		$f.on('submit',function(e){
			e.preventDefault();var $b=$(this).find('button[type="submit"]').prop('disabled',true).text('<?php echo esc_js(__('Saving...','gmcq'));?>');
			$r.hide().removeClass('notice-success notice-error').empty();
			var mv=''; if(hm){var m_sel=$ms.val();mv=(m_sel&&m_sel!==''&&m_sel!=='__other__')?m_sel:$mn.val().trim();}else{mv=$mn.val().trim();}
			var sv='',ssv=$ss.val(); if($ssw.is(':hidden')){sv=$sn.val().trim();}else if(ssv&&ssv!==''&&ssv!=='__other__')sv=ssv;else if(ssv==='__other__')sv=$sn.val().trim();
			if(!mv){$r.css('border-color','#dc3232').addClass('notice-error').html('<p><?php echo esc_js(__('Please select or type a main category.','gmcq'));?></p>').fadeIn();$b.prop('disabled',false).text('<?php echo esc_js(__('Save Category','gmcq'));?>');return;}
			$.post(ajax,{action:'gmcq_add_category',main_category:mv,sub_category:sv,description:$('#gmcq-cat-desc').val(),_ajax_nonce:nonce},function(r){
				if(r.success){$r.css('border-color','#46b450').addClass('notice-success').html('<p>'+r.data.message+'</p>').fadeIn();setTimeout(function(){window.location.href=list;},1000);}
				else{$r.css('border-color','#dc3232').addClass('notice-error').html('<p>'+(r.data.message||'<?php echo esc_js(__('Error saving category.','gmcq'));?>')+'</p>').fadeIn();$b.prop('disabled',false).text('<?php echo esc_js(__('Save Category','gmcq'));?>');}
			}).fail(function(){$r.css('border-color','#dc3232').addClass('notice-error').html('<p><?php echo esc_js(__('Server error.','gmcq'));?></p>').fadeIn();$b.prop('disabled',false).text('<?php echo esc_js(__('Save Category','gmcq'));?>');});
		});
	});
	</script><?php
}

function gmcq_render_category_edit_form(int $category_id):void{
	$cat=gmcq_get_category($category_id);if(!$cat){echo '<div class="notice notice-error"><p>'.esc_html__('Category not found.','gmcq').'</p></div>';return;}
	$mr=gmcq_get_categories(array('parent_only'=>true,'filter'=>'active'));$mains=$mr['categories'];
	$lu=admin_url('admin.php?page=gmcq-categories');
	$cat_name_esc=esc_attr($cat->name);$cat_slug_esc=esc_attr($cat->slug);$cat_desc_esc=esc_textarea($cat->description);
	?><div class="wrap gmcq-dashboard-wrap"><h1><?php esc_html_e('Edit Category','gmcq');?></h1>
	<div class="gmcq-card" style="max-width:800px">
	<form id="gmcq-edit-category-form">
	<input type="hidden" name="action" value="gmcq_update_category">
	<input type="hidden" name="id" value="<?php echo esc_attr($cat->id);?>">
	<input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr(wp_create_nonce('gmcq_category_nonce'));?>">
	<table class="form-table"><tr><th scope="row"><label for="gmcq-edit-cat-name"><?php esc_html_e('Name','gmcq');?> <span style="color:red">*</span></label></th>
	<td><input name="name" type="text" id="gmcq-edit-cat-name" class="regular-text" value="<?php echo $cat_name_esc;?>" required></td></tr>
	<tr><th scope="row"><label for="gmcq-edit-cat-slug"><?php esc_html_e('Slug','gmcq');?></label></th>
	<td><input name="slug" type="text" id="gmcq-edit-cat-slug" class="regular-text" value="<?php echo $cat_slug_esc;?>"><p class="description"><?php esc_html_e('Leave empty to auto-generate from the name.','gmcq');?></p></td></tr>
	<tr><th scope="row"><label for="gmcq-edit-cat-parent"><?php esc_html_e('Parent Category','gmcq');?></label></th>
	<td><select name="parent_id" id="gmcq-edit-cat-parent"><option value=""><?php esc_html_e('None (Top Level)','gmcq');?></option>
	<?php foreach($mains as$main){if($main->id===$cat->id)continue;?><option value="<?php echo esc_attr($main->id);?>" <?php selected($cat->parent_id,$main->id);?>><?php echo esc_html($main->name);?></option><?php }?></select>
	<p class="description"><?php esc_html_e('Categories support 2-level hierarchy only.','gmcq');?></p></td></tr>
	<tr><th scope="row"><label for="gmcq-edit-cat-desc"><?php esc_html_e('Description','gmcq');?></label></th>
	<td><textarea name="description" id="gmcq-edit-cat-desc" rows="4" class="regular-text"><?php echo $cat_desc_esc;?></textarea></td></tr>
	</table><p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Update Category','gmcq');?></button>
	<a href="<?php echo esc_url($lu);?>" class="button button-secondary"><?php esc_html_e('Cancel','gmcq');?></a></p></form>
	<div id="gmcq-edit-form-response" role="alert" aria-live="polite" style="margin-top:15px;padding:10px;display:none;border-left:4px solid transparent"></div></div></div>
	<script>
	jQuery(document).ready(function($){$('#gmcq-edit-category-form').on('submit',function(e){
		e.preventDefault();var $b=$(this).find('button[type="submit"]').prop('disabled',true).text('<?php echo esc_js(__('Updating...','gmcq'));?>');var $r=$('#gmcq-edit-form-response').hide();
		$.post(gmcqAdmin.ajaxUrl,$(this).serialize(),function(r){if(r.success){$r.css('border-color','#46b450').text(r.data.message).fadeIn();setTimeout(function(){window.location.href='<?php echo esc_js(admin_url('admin.php?page=gmcq-categories'));?>';},1000);}else{$r.css('border-color','#dc3232').text(r.data.message||'<?php echo esc_js(__('Error updating category.','gmcq'));?>').fadeIn();$b.prop('disabled',false).text('<?php echo esc_js(__('Update Category','gmcq'));?>');}
		}).fail(function(){$r.css('border-color','#dc3232').text('<?php echo esc_js(__('Server error.','gmcq'));?>').fadeIn();$b.prop('disabled',false).text('<?php echo esc_js(__('Update Category','gmcq'));?>');});});
	});
	</script><?php
}
