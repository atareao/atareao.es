# podcast-transcribe-diagnostics

Arregla el diagnóstico del pipeline de transcripciones: el motivo de un fallo sale
de la salida real del comando (`stdout` incluido) y lleva el código de salida o la
señal que terminó el proceso, en vez del «sin detalle» sistemático.
