$ErrorActionPreference = 'Stop'
function Receive-CdpMessage { param($ws) $buffer = New-Object byte[] 65536; $segment = [ArraySegment[byte]]::new($buffer); $stream = New-Object System.IO.MemoryStream; do { $result = $ws.ReceiveAsync($segment, [Threading.CancellationToken]::None).GetAwaiter().GetResult(); if ($result.MessageType -eq [System.Net.WebSockets.WebSocketMessageType]::Close) { throw 'WebSocket closed' }; $stream.Write($buffer,0,$result.Count) } while(-not $result.EndOfMessage); $stream.Position=0; $reader=New-Object System.IO.StreamReader($stream,[Text.Encoding]::UTF8); $text=$reader.ReadToEnd(); if([string]::IsNullOrWhiteSpace($text)){return $null}; return $text|ConvertFrom-Json }
function Send-CdpCommand { param($ws,[int]$id,[string]$method,$params) $payload=@{id=$id;method=$method;params=$params}|ConvertTo-Json -Depth 20 -Compress; $bytes=[Text.Encoding]::UTF8.GetBytes($payload); $segment=[ArraySegment[byte]]::new($bytes); $ws.SendAsync($segment,[System.Net.WebSockets.WebSocketMessageType]::Text,$true,[Threading.CancellationToken]::None).GetAwaiter().GetResult() }
function Invoke-Cdp { param($ws,[string]$method,$params) $script:cdpId++; $id=$script:cdpId; Send-CdpCommand $ws $id $method $params; while($true){ $msg=Receive-CdpMessage $ws; if($null -eq $msg){continue}; if($msg.id -eq $id){ if($msg.error){ throw ($msg.error|ConvertTo-Json -Compress)}; return $msg.result }; $script:eventLog += ,$msg } }
function Wait-ForEvent { param($ws,[string]$method,[int]$timeoutMs=15000) $deadline=[DateTime]::UtcNow.AddMilliseconds($timeoutMs); while([DateTime]::UtcNow -lt $deadline){ for($i=0;$i -lt $script:eventLog.Count;$i++){ if($script:eventLog[$i].method -eq $method){ $event=$script:eventLog[$i]; $script:eventLog.RemoveAt($i); return $event } } $msg=Receive-CdpMessage $ws; if($null -ne $msg){ if($msg.method -eq $method){ return $msg }; $script:eventLog += ,$msg } } throw "Timed out waiting for $method" }
function Eval-Json { param($ws,[string]$expression) $result=Invoke-Cdp $ws 'Runtime.evaluate' @{expression=$expression; returnByValue=$true; awaitPromise=$true}; if($result.exceptionDetails){ throw ($result.exceptionDetails|ConvertTo-Json -Depth 8) }; return $result.result.value }
$target = Invoke-RestMethod -Method Put 'http://127.0.0.1:9224/json/new?about:blank'
$ws=[System.Net.WebSockets.ClientWebSocket]::new(); $ws.ConnectAsync([Uri]$target.webSocketDebuggerUrl,[Threading.CancellationToken]::None).GetAwaiter().GetResult(); $script:cdpId=0; $script:eventLog=New-Object System.Collections.ArrayList
Invoke-Cdp $ws 'Page.enable' @{}|Out-Null; Invoke-Cdp $ws 'Runtime.enable' @{}|Out-Null; Invoke-Cdp $ws 'Network.enable' @{}|Out-Null
Invoke-Cdp $ws 'Network.setCookie' @{ name='SUKHYANG_ERP'; value='1e8ebfb860765f33e58e5867186bddde'; url='http://localhost:8000/' } | Out-Null
Invoke-Cdp $ws 'Page.navigate' @{ url='http://localhost:8000/dashboard/settings/base-info/cover' } | Out-Null
Wait-ForEvent $ws 'Page.loadEventFired' 20000 | Out-Null
Start-Sleep -Seconds 4
$result = Eval-Json $ws @"
(() => ({
  title: document.title,
  headers: Array.from(document.querySelectorAll('thead th')).map((th, index) => ({
    index,
    text: th.textContent.trim(),
    html: th.innerHTML,
    hasResizer: !!th.querySelector('.dt-column-resizer')
  }))
}))()
"@
$result | ConvertTo-Json -Depth 8