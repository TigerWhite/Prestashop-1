<h4>{l s='New Fields Tutorial' mod='newfieldstut'}</h4>
<div class="separation"></div>
<table>
	<!-- THis first row has been added by Nemo! -->
	<tr>
		<td class="col-left">
			<label>{l s='Our New Field:'}</label>
		</td>
		<td>
			{include file="controllers/products/input_text_lang.tpl"
				languages=$languages
				input_name='custom_field'
				input_value=$custom_field}
			<p class="preference_description">{l s='Simply a new custom field'}. <strong>{l s='ie: something here as a hint'}</strong></p>
		</td>
	</tr>
</table>