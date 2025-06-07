#!/bin/bash

#######################################################################################

info() { echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
error() { echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

#######################################################################################

basedir=$(dirname "$(realpath $0)")
basefile=$(basename "$(realpath $0)")
basepath=$basedir/$basefile

#######################################################################################

git add .

#######################################################################################

commit_msg=""

addToCommitMsg() { commit_msg+="$1"$'\n'; }

version=$(grep '"message"' ${basedir}/version.json | sed -E 's/.*"message": *"([^"]+)".*/\1/')

addToCommitMsg "$version"
addToCommitMsg ""
addToCommitMsg "----------------------------------------------------- "
addToCommitMsg ""

if [ -n "$1" ]; then
    addToCommitMsg "$1"
fi

addToCommitMsg ""
addToCommitMsg "----------------------------------------------------- "
addToCommitMsg ""
addToCommitMsg "filetracking:"
addToCommitMsg ""

git_status=$(git status --short | sort)
while read -r status file; do
    case "$status" in
        A)   addToCommitMsg "$(printf '%-18s %s' "Added" "$file")" ;;
        M)   addToCommitMsg "$(printf '%-18s %s' "Modified" "$file")" ;;
        D)   addToCommitMsg "$(printf '%-18s %s' "Deleted" "$file")" ;;
        R*)  read _ old new <<< "$status $file"
             addToCommitMsg "$(printf '%-18s %s %s' "Renamed" "$old" "$new")" ;;
        AM)  addToCommitMsg "$(printf '%-18s %s' "Added+Modified" "$file")" ;;
        RM)  addToCommitMsg "$(printf '%-18s %s' "Renamed+Modified" "$file")" ;;
        \?\?) addToCommitMsg "$(printf '%-18s %s' "Untracked" "$file")" ;;
        *)   addToCommitMsg "$(printf '%-18s %s' "$status" "$file")" ;;
    esac
done <<< "$git_status"

#######################################################################################

for i in {1..10}; do echo ""; done
echo "$commit_msg"
for i in {1..10}; do echo ""; done
#git commit -m "$commit_msg"

#######################################################################################
