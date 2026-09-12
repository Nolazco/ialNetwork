// Puente de WhatsApp para avisos de modulado — corre directo en el servidor de
// producción (Node puro vía Baileys, sin Chromium: el hosting compartido no
// permite los procesos/hilos que Puppeteer necesita). Ver src/Notification/WhatsAppSender.php.
//
// No se sube por Composer/deploy normal: se copia una sola vez a
// /home/<usuario>/whatsapp-bridge/server.js y se corre con nohup + cron de
// keep-alive (ver scripts/whatsapp-bridge-keepalive.sh).
'use strict';

const http = require('http');
const { Boom } = require('@hapi/boom');
const makeWASocket = require('@whiskeysockets/baileys').default;
const { DisconnectReason, useMultiFileAuthState } = require('@whiskeysockets/baileys');

const PORT = process.env.WHATSAPP_BRIDGE_PORT || 3301;
const TOKEN = process.env.WHATSAPP_BRIDGE_TOKEN || '';
const PAIRING_NUMBER = process.env.WHATSAPP_PAIRING_NUMBER || '';

if (TOKEN === '') {
    console.error('Falta WHATSAPP_BRIDGE_TOKEN — no se inicia el servidor sin token.');
    process.exit(1);
}

let sock = null;

function formatJid(destino) {
    const limpio = destino.toString().trim();

    if (limpio.endsWith('@g.us') || limpio.includes('-')) {
        return limpio.endsWith('@g.us') ? limpio : `${limpio}@g.us`;
    }

    let soloDigitos = limpio.replace(/[^0-9]/g, '');

    // Los numeros se capturan en la app a 10 digitos, sin codigo de pais (ver
    // PhoneListParser y Company::whatsapp) -- sin este caso el JID queda sin
    // "52" y WhatsApp nunca entrega el mensaje, aunque el puente responda
    // success:true (sendMessage no valida que el JID exista de verdad).
    if (soloDigitos.length === 10) {
        soloDigitos = '521' + soloDigitos;
    } else if (soloDigitos.startsWith('52') && !soloDigitos.startsWith('521') && soloDigitos.length === 12) {
        soloDigitos = '521' + soloDigitos.substring(2);
    }

    return `${soloDigitos}@s.whatsapp.net`;
}

async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState('auth_info_baileys');

    sock = makeWASocket({
        auth: state,
        printQRInTerminal: false,
        defaultQueryTimeoutMs: undefined,
    });

    if (!sock.authState.creds.registered && PAIRING_NUMBER !== '') {
        // requestPairingCode falla con "Connection Closed" (428) si se pide
        // antes de que el socket termine de abrir la conexión — el momento
        // exacto varia entre versiones de Baileys, asi que reintenta en vez
        // de depender de un evento especifico.
        let code = null;
        for (let intento = 1; intento <= 8 && code === null; intento++) {
            await new Promise((resolve) => setTimeout(resolve, 2000));
            try {
                code = await sock.requestPairingCode(PAIRING_NUMBER);
            } catch (err) {
                console.log(`Intento ${intento} de pedir codigo fallo: ${err.message}`);
            }
        }

        if (code === null) {
            console.error('No se pudo obtener el codigo de vinculacion tras varios intentos.');
        } else {
            console.log(`\n==================================================`);
            console.log(`CODIGO DE VINCULACION: ${code}`);
            console.log(`En WhatsApp: Ajustes > Dispositivos vinculados > Vincular con numero de telefono`);
            console.log(`==================================================\n`);
        }
    }

    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect } = update;

        if (connection === 'open') {
            console.log('✅ Conectado a WhatsApp.');
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error instanceof Boom
                ? lastDisconnect.error.output?.statusCode
                : null;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

            console.log(`Conexion cerrada (status ${statusCode}). Reconectar: ${shouldReconnect}`);

            if (shouldReconnect) {
                connectToWhatsApp();
            }
        }
    });

    sock.ev.on('creds.update', saveCreds);
}

const server = http.createServer((req, res) => {
    if (req.method !== 'POST' || req.url !== '/enviar') {
        res.writeHead(404, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: false, error: 'not found' }));
        return;
    }

    if (req.headers.authorization !== `Bearer ${TOKEN}`) {
        res.writeHead(401, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: false, error: 'unauthorized' }));
        return;
    }

    let body = '';
    req.on('data', (chunk) => { body += chunk; });
    req.on('end', async () => {
        let numero, mensaje;
        try {
            ({ numero, mensaje } = JSON.parse(body || '{}'));
        } catch (e) {
            res.writeHead(400, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ success: false, error: 'JSON invalido' }));
            return;
        }

        if (!numero || numero.toString().trim() === '') {
            res.writeHead(400, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ success: false, error: 'El numero de destino no puede estar vacio.' }));
            return;
        }

        if (!sock || !sock.user) {
            res.writeHead(503, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ success: false, error: 'WhatsApp no esta conectado todavia.' }));
            return;
        }

        try {
            const jid = formatJid(numero);
            await sock.sendMessage(jid, { text: mensaje || '' });
            console.log(`✉️ Mensaje enviado a: ${jid}`);
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ success: true, status: 'sent' }));
        } catch (error) {
            console.error(`❌ Error enviando a '${numero}':`, error.message);
            res.writeHead(500, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ success: false, error: error.message }));
        }
    });
});

connectToWhatsApp();

// Solo localhost: nadie fuera de este servidor necesita alcanzarlo (ver
// WhatsAppSender.php, que ya vive en la misma máquina).
server.listen(PORT, '127.0.0.1', () => {
    console.log(`🚀 Puente de WhatsApp escuchando en 127.0.0.1:${PORT}`);
});
