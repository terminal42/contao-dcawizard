<div class="dcaWizardEditBoxWrapper">
	<div id="dcaWizardEditBox_<?php echo $this->dcaField; ?>">
	</div>
	<div class="clr"></div>
</div>
<div id="dcaWizardTable_<?php echo $this->dcaField; ?>" class="dcaWizardTable">
	<table>
		<thead>
			<tr>
				<?php foreach($this->tableColumns as $col => $lbl): ?>
				<td class="<?php echo $col; ?>"><?php echo $lbl; ?></td>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php if($this->noItemsYet == 1): ?>
			<tr class="noitem">
				<td colspan="<?php echo $this->colspan; ?>"><?php echo $this->noItemsYetMsg; ?></td>
			</tr>
			<?php else: ?>
				<?php $count = 0; ?>
				<?php foreach($this->arrItems as $id => $arrColumn): ?>
				<tr id="itemId_<?php echo $id; ?>" class="item <?php $count++; echo ($count % 2 == 1) ? 'odd' : 'even'; ?>">
					<?php foreach($arrColumn as $col => $val): ?>
					<td class="<?php echo $col; ?>"><?php echo $val; ?></td>
					<?php endforeach; ?>
					<td class="operations">
						<div class="operations">
							<a href="<?php echo $this->baseUrl . '/main.php?do=doWizard&table=' . $this->foreignDCA . '&act=edit&id=' . $id; ?>" class="edit">
								<img width="14" height="16" class="tl_listwizard_img" alt="<?php echo $this->editItemAlt; ?>" src="system/themes/<?php echo $this->theme; ?>/images/edit.gif" />
							</a>
							<a href="<?php echo $this->baseUrl . '/main.php?do=doWizard&table=' . $this->foreignDCA . '&act=delete&id=' . $id; ?>" class="delete">
								<img width="14" height="16" class="tl_listwizard_img" alt="<?php echo $this->deleteItemAlt; ?>" src="system/themes/<?php echo $this->theme; ?>/images/delete.gif" />
							</a>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="<?php echo $this->colspan; ?>">
					<a class="add_item" href="<?php echo $this->addItemUrl; ?>">
						<?php echo $this->addItemMsg; ?> <img width="14" height="16" class="tl_listwizard_img" alt="<?php echo $this->addItemMsg; ?>" src="system/themes/<?php echo $this->theme; ?>/images/new.gif" />
					</a>
				</td>
			</tr>
		</tfoot>
	</table>
</div>


<script type="text/javascript">
<!--//--><![CDATA[//><!--
window.addEvent('domready',function(){
	new dcaWizard({
		dcaWizardEditBox: 'dcaWizardEditBox_' + '<?php echo $this->dcaField; ?>',
		dcaWizardTable: 'dcaWizardTable_' + '<?php echo $this->dcaField; ?>',
		dcaTable: '<?php echo $this->dcaTable; ?>',
		dcaField: '<?php echo $this->dcaField; ?>',
		dcaPalette: '<?php echo $this->dcaPalette; ?>',
		foreignDCA: '<?php echo $this->foreignDCA; ?>',
		deleteConfirmMsg: '<?php echo $this->deleteConfirmMsg; ?>',
		parentId: <?php echo $this->parentId; ?>,
		editItemAlt: '<?php echo $this->editItemAlt; ?>',
		deleteItemAlt: '<?php echo $this->deleteItemAlt; ?>',
		baseUrl: '<?php echo $this->baseUrl; ?>',
		saveLbl: '<?php echo $this->saveLbl; ?>',
		saveNcloseLbl: '<?php echo $this->saveNcloseLbl; ?>',
		cancelLbl: '<?php echo $this->cancelLbl; ?>',
		saveNcreateLbl: '<?php echo $this->saveNcreateLbl; ?>',
		closeLbl: '<?php echo $this->closeLbl; ?>',
		theme: '<?php echo $this->theme; ?>',
		failureMsg: '<?php echo $this->failureMsg; ?>'
	});
});
//--><!]]>
</script>
