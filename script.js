let command
let mod_permission
let vip_permission
const minViews = {{generalMinViews}}
const video = document.querySelector("#clip");
const container = document.querySelector('.video-container')
const channel = document.querySelector('.channel')

let test2

window.addEventListener('onWidgetLoad', (obj) => {
    console.log(`Debug: widget loaded`)
    const fieldData = obj.detail.fieldData
    command = fieldData["generalCommand"]
    mod_permission = fieldData['permissionsMods']
    vip_permission = fieldData['permissionsVIPs']
    console.log(`Debug: ${command} | Mods: ${mod_permission} | VIPs: ${vip_permission}`)
})

window.addEventListener('onEventReceived', (obj) => {
    console.log("Event Object: ", obj)

    let eventType = obj["detail"]["listener"]
    console.log(`Debug: ${eventType} (type)`)

    if ( eventType === 'message' ) {
        let eventContent = obj["detail"]["event"]["data"]
        let message = eventContent["text"]
    
        console.log(`Debug: ${eventContent['nick']}: ${message}`)

        if ( senderHasPermission(eventContent) && message.startsWith(`${command} `) ) {
            getRandomClip(message) 
        }
    }
})

function senderHasPermission(sender) {
    console.log(sender['badges'])
    const badges = sender['badges'].filter(filterBadges).map( badge => badge['type'] )
    console.log(`badges: ${badges}`)

    if ( badges.includes('broadcaster') ) {
        return true
    }

    if ( mod_permission && badges.includes('moderator') ) {
        return true
    }

    if ( vip_permission && badges.includes('vip') ) {
        return true
    }

    return false
}

function filterBadges(badge) {
	if ( badge['type'] === 'broadcaster' ) {
        return true
    }
        
    if ( badge['type'] === 'moderator' ) {
        return true
    }

	if ( badge['type'] === 'vip' ) {
        return true
    }

    return false  
}

function getRandomClip(message) {
    let content = message.replace(`${command} `, "")
    let channel = content.split(" ")[0]

    console.log(`Debug: ${content} (command parameter)`)
    console.log(`Debug: ${channel} (extracted channel)`)

    if ( !isPlaying() ) {
        fetch(`https://myurl.com/twitch/twitch-random-topclip.php?username=${channel}&views=${minViews}`)
            .then(response => response.json())
            .then(data => {
                console.log(`Debug: fetched ${data["url"]}`)
                if ( !isPlaying() ) {
                    playVideo(data)
                }
            })
    } else {
        console.log(`Debug: clip is still playing, new ${command} has been ignored`)
    }
}

function isPlaying() {
    return container.classList.contains('playing') 
}

function playVideo(data){
    console.log("API Response: ", data)
    channel.innerHTML = data['channel']
    video.src = data["url"];
    video.play()
}

function resetPlayer(video) {
    console.log(`Debug: reset player`)
    container.classList.remove('playing')
    video.pause()
}

video.addEventListener(
    'loadeddata', 
    () => {
        console.log(`Debug: video loaded, playing now`)
        container.classList.add('playing')
    },
    { once: false }
)

video.addEventListener(
    'ended', 
    () => {
        console.log(`Debug: video ended`)
        resetPlayer(video)
    },
    { once: false }
)
