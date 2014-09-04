{capture name=path}{l s='COD Availability' mod='CODAvailabilaty'}{/capture}
<div id="sendfriendpage">	
	{include file="$tpl_dir/errors.tpl"}
			<form method="post" action="{$request_uri}">
				<fieldset>
					<div id="pincode">
                                            <p class="text-capitalize"><i class="icon-briefcase"></i>Enter your pincode to know cash on delivery availability<p>
						<p class="text">
							
							<input type="text" class="text input-small" style="border: 1px solid rgb(189, 194, 201);height: 33px" id="friend-name" name="code" value="{if isset($smarty.post.code)}{$smarty.post.code|escape:'html':'UTF-8'|stripslashes}{/if}" />
						
							<input type="submit" name="codcheck" value="{l s='send' mod='CODAvailabilaty'}" class="btn btn-success" />						
					</p>
                                        </div>
				</fieldset>
			</form>

	
</div>