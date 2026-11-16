#!/bin/sh
# Installation de kory sous macOS et Linux (K05, TRD §18.1) : curl -fsSL https://koryhub.dev/install.sh | sh
# Télécharge kory_<os>_<arch> et checksums.txt, vérifie l'empreinte SHA-256, installe dans
# ~/.local/bin (sans sudo) et explique comment l'ajouter au PATH. Empreinte différente : rien n'est
# installé. Règle ensuite l'adresse de l'API du site qui sert le script. Le site remplace
# __KORY_VERSION__, __KORY_DOWNLOAD_URL__ et __KORY_API_URL__ en servant ce fichier.
set -eu

VERSION='__KORY_VERSION__'
BASE_URL='__KORY_DOWNLOAD_URL__'
API_URL='__KORY_API_URL__'
DIR="${HOME}/.local/bin"

case "${LANG:-en}" in
  fr*) T_DOWNLOAD='Téléchargement…'; T_VERIFIED='Empreinte SHA-256 vérifiée'; T_MISMATCH="Empreinte différente : le fichier téléchargé n'est pas celui publié."; T_NOTHING="Rien n'a été installé."; T_INSTALLED='Installé dans'; T_PATH='Ajoutez ce dossier à votre PATH, par exemple dans ~/.profile :'; T_NEXT='Ouvrez un nouveau terminal, puis tapez : kory version'; T_UNSUPPORTED='Système ou processeur non pris en charge :'; T_TOOL='Il faut curl ou wget, et sha256sum ou shasum.' ;;
  ar*) T_DOWNLOAD='جارٍ التنزيل…'; T_VERIFIED='تم التحقق من بصمة SHA-256'; T_MISMATCH='البصمة مختلفة: الملف المنزّل ليس الملف المنشور.'; T_NOTHING='لم يُثبَّت أي شيء.'; T_INSTALLED='ثُبّت في'; T_PATH='أضف هذا المجلد إلى PATH، مثلًا في ~/.profile:'; T_NEXT='افتح طرفية جديدة ثم اكتب: kory version'; T_UNSUPPORTED='نظام أو معالج غير مدعوم:'; T_TOOL='يلزم curl أو wget، و sha256sum أو shasum.' ;;
  pt*) T_DOWNLOAD='A transferir…'; T_VERIFIED='Impressão digital SHA-256 verificada'; T_MISMATCH='Impressão digital diferente: o ficheiro transferido não é o publicado.'; T_NOTHING='Nada foi instalado.'; T_INSTALLED='Instalado em'; T_PATH='Adicione esta pasta ao seu PATH, por exemplo em ~/.profile:'; T_NEXT='Abra um novo terminal e escreva: kory version'; T_UNSUPPORTED='Sistema ou processador não suportado:'; T_TOOL='É preciso curl ou wget, e sha256sum ou shasum.' ;;
  *) T_DOWNLOAD='Downloading...'; T_VERIFIED='SHA-256 checksum verified'; T_MISMATCH='Checksum mismatch: the downloaded file is not the published one.'; T_NOTHING='Nothing was installed.'; T_INSTALLED='Installed in'; T_PATH='Add this folder to your PATH, for example in ~/.profile:'; T_NEXT='Open a new terminal, then type: kory version'; T_UNSUPPORTED='Unsupported system or processor:'; T_TOOL='curl or wget, and sha256sum or shasum, are required.' ;;
esac

case "$(uname -s)" in
  Linux) OS=linux ;;
  Darwin) OS=darwin ;;
  *) echo "$T_UNSUPPORTED $(uname -s)" >&2; exit 1 ;;
esac
case "$(uname -m)" in
  x86_64|amd64) ARCH=amd64 ;;
  aarch64|arm64) ARCH=arm64 ;;
  *) echo "$T_UNSUPPORTED $(uname -m)" >&2; exit 1 ;;
esac

if command -v curl >/dev/null 2>&1; then
  fetch() { curl -fsSL -o "$2" "$1"; }
elif command -v wget >/dev/null 2>&1; then
  fetch() { wget -q -O "$2" "$1"; }
else
  echo "$T_TOOL" >&2; exit 1
fi
if command -v sha256sum >/dev/null 2>&1; then
  sha() { sha256sum "$1" | cut -d ' ' -f 1; }
elif command -v shasum >/dev/null 2>&1; then
  sha() { shasum -a 256 "$1" | cut -d ' ' -f 1; }
else
  echo "$T_TOOL" >&2; exit 1
fi

NAME="kory_${OS}_${ARCH}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "kory ${VERSION} · ${OS}/${ARCH}"
echo "$T_DOWNLOAD"
fetch "${BASE_URL}/${VERSION}/${NAME}" "${TMP}/${NAME}"
fetch "${BASE_URL}/${VERSION}/checksums.txt" "${TMP}/checksums.txt"

EXPECTED="$(awk -v n="$NAME" '$2 == n { print tolower($1) }' "${TMP}/checksums.txt")"
ACTUAL="$(sha "${TMP}/${NAME}")"
if [ -z "$EXPECTED" ] || [ "$EXPECTED" != "$ACTUAL" ]; then
  echo "$T_MISMATCH" >&2
  echo "$T_NOTHING" >&2
  exit 1
fi
echo "$T_VERIFIED"

mkdir -p "$DIR"
mv "${TMP}/${NAME}" "${DIR}/kory"
chmod 755 "${DIR}/kory"
echo "$T_INSTALLED ${DIR}/kory"
"${DIR}/kory" config set api-url "$API_URL"

case ":${PATH}:" in
  *":${DIR}:"*) ;;
  *) echo "$T_PATH"; echo "  export PATH=\"\$HOME/.local/bin:\$PATH\"" ;;
esac
echo "$T_NEXT"
