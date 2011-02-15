
{include file='clip_admin_header.tpl'}

<div class="z-admincontainer">
    <div class="z-adminpageicon">{img modname='core' src='exec.png' set='icons/large' __alt='Show code'}</div>

    <h2>{gt text='Show code'}</h2>

    {clip_admin_submenu tid=$pubtype.tid mode=$mode}

    {switch expr=$mode}
        {case expr='input'}
            {assign var='cliptplname' value='form_all.tpl'}
            {assign var='clipfolder' value=$pubtype.inputset}
            <h3>{gt text='Input form template'}</h3>
        {/case}
        {case expr='outputlist'}
            {assign var='cliptplname' value='list.tpl'}
            {assign var='clipfolder' value=$pubtype.outputset}
            <h3>{gt text='Publication list template'}</h3>
        {/case}
        {case expr='outputfull'}
            {assign var='cliptplname' value='display.tpl'}
            {assign var='clipfolder' value=$pubtype.outputset}
            <h3>{gt text='Publication display template'}</h3>
        {/case}
    {/switch}

    <p class="z-warningmsg">
        {gt text='Create a template file named <strong>%1$s</strong> with this code, and store it in the the directory: <strong>/config/templates/Clip/%2$s/%1$s</strong>, or within your theme in the <strong>/themes/YourTheme/templates/modules/Clip/%2$s/%1$s</strong>.' tag1=$cliptplname tag2=$clipfolder}
    </p>

    <pre class="clip-showcode">{$code}</pre>
</div>
