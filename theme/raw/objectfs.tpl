{include file='header.tpl'}

{if $sitedata}
  <div id="site-stats-wrap" class="panel-items js-masonry" data-masonry-options='{ "itemSelector": ".panel" }'>
    <div class="panel panel-info">
      <h3 class="panel-heading">{$sitedata.name}: {str tag=object_status:page section=module.objectfs} <span class="icon icon-info pull-right" role="presentation" aria-hidden="true"></span></h3>

      <table class="table">
        <tr>
          <th>Location</th>
          <th>Objectcount</th>
          <th>Objectsum</th>
        </tr>

        <tr>
          <td>{str tag=object_status:location:local section=module.objectfs}</td>
          <td>{$sitedata.local.objectcount}</td>
          <td>{$sitedata.local.objectsum|display_size}</td>
        </tr>

        <tr>
          <td>{str tag=object_status:location:external section=module.objectfs}</td>
          <td>{$sitedata.remote.objectcount}</td>
          <td>{$sitedata.remote.objectsum|display_size}</td>
        </tr>

        <tr>
          <td>{str tag=object_status:location:duplicated section=module.objectfs}</td>
          <td>{$sitedata.duplicated.objectcount}</td>
          <td>{$sitedata.duplicated.objectsum|display_size}</td>
        </tr>

        <tr>
          <td>{str tag=object_status:location:error section=module.objectfs}</td>
          <td>{$sitedata.error.objectcount}</td>
          <td>{$sitedata.error.objectsum|display_size}</td>
        </tr>

        <tr>
          <td>{str tag=object_status:location:total section=module.objectfs}</td>
          <td>{$sitedata.totalcount}</td>
          <td>{$sitedata.totalsum|display_size}</td>
        </tr>

        <tr>
          <td><a href="{$WWWROOT}admin/extensions/pluginconfig.php?plugintype=module&pluginname=objectfs">Settings</a></td>
        </tr>
      </table>

    </div>

  </div>
{/if}


{include file='footer.tpl'}
