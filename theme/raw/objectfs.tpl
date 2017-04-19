{include file='header.tpl'}

{if $sitedata}
  <div id="site-stats-wrap" class="panel-items js-masonry" data-masonry-options='{ "itemSelector": ".panel" }'>
    <div class="panel panel-info">
      <h3 class="panel-heading"> {str tag=object_status:location section=module.objectfs} <span class="icon icon-info pull-right" role="presentation" aria-hidden="true"></span></h3>

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

    <div class="panel panel-info">
      <h3 class="panel-heading"> {str tag=object_status:fileranges section=module.objectfs} <span class="icon icon-info pull-right" role="presentation" aria-hidden="true"></span></h3>

      <table class="table">
        <tr>
          <th>Size ranges</th>
          <th>Objectcount</th>
          <th>Objectsum</th>
        </tr>

        {foreach from=$sitedata['logsize'] key=key item=item}
          <tr>
            <td>{$sitedata.logsize.$key.0}</td>
            <td>{$sitedata.logsize.$key.1}</td>
            <td>{$sitedata.logsize.$key.2}</td>
          </tr>
        {/foreach}

      </table>

    </div>

    <div class="panel panel-info">
      <h3 class="panel-heading"> {str tag=object_status:mimetypes section=module.objectfs} <span class="icon icon-info pull-right" role="presentation" aria-hidden="true"></span></h3>

      <table class="table">
        <tr>
          <th>Mimetype</th>
          <th>Objectcount</th>
          <th>Objectsum</th>
        </tr>

        {foreach from=$sitedata['mimetypes'] key=key item=item}
          <tr>
            <td>{$sitedata.mimetypes.$key.0}</td>
            <td>{$sitedata.mimetypes.$key.1}</td>
            <td>{$sitedata.mimetypes.$key.2}</td>
          </tr>
        {/foreach}

      </table>

    </div>

  </div>
{/if}


{include file='footer.tpl'}
