Set WshShell = CreateObject("WScript.Shell")
Dim fso: Set fso = CreateObject("Scripting.FileSystemObject")
Dim currentDir: currentDir = fso.GetParentFolderName(WScript.ScriptFullName)
WshShell.CurrentDirectory = currentDir & "\dist-desktop"
WshShell.Run """" & currentDir & "\dist-desktop\mymind.exe""", 0, False
