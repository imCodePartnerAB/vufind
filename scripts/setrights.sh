  #setfacl -Rb vufind
  #setfacl -Rb /srv/github/vufind
  #setfacl -Rb /usr/local/vufind
  sudo setfacl -dRm g:ragkoh:rwX,u:ragkoh:rwX /srv/github/vufind
  sudo setfacl -Rm g:ragkoh:rwX,u:ragkoh:rwX /srv/github/vufind
  sudo setfacl -Rm g:ragkoh:rwX,u:ragkoh:rwX /usr/local/vufind
  sudo setfacl -dRm g:ragkoh:rwX,u:ragkoh:rwX /usr/local/vufind
  sudo setfacl -Rm g:ragkoh:rwX,u:ragkoh:rwX /var/log/vufind
  sudo setfacl -dRm g:ragkoh:rwX,u:ragkoh:rwX /var/log/vufind
  sudo setfacl -Rm g:ragkoh:rwX,u:ragkoh:rwX /tmp
  sudo setfacl -dRm g:ragkoh:rwX,u:ragkoh:rwX /tmp
  

  sudo setfacl -dRm g:parjoh:rwX,u:parjoh:rwX /srv/github/vufind
  sudo setfacl -Rm g:parjoh:rwX,u:parjoh:rwX /srv/github/vufind
  sudo setfacl -Rm g:parjoh:rwX,u:parjoh:rwX /usr/local/vufind
  sudo setfacl -dRm g:parjoh:rwX,u:parjoh:rwX /usr/local/vufind
  sudo setfacl -Rm g:parjoh:rwX,u:parjoh:rwX /var/log/vufind
  sudo setfacl -dRm g:parjoh:rwX,u:parjoh:rwX /var/log/vufind
  sudo setfacl -Rm g:parjoh:rwX,u:parjoh:rwX /tmp
  sudo setfacl -dRm g:parjoh:rwX,u:parjoh:rwX /tmp

  sudo setfacl -dRm g:jacsan:rwX,u:jacsan:rwX /srv/github/vufind
  sudo setfacl -Rm g:jacsan:rwX,u:jacsan:rwX /srv/github/vufind
  sudo setfacl -Rm g:jacsan:rwX,u:jacsan:rwX /usr/local/vufind
  sudo setfacl -dRm g:jacsan:rwX,u:jacsan:rwX /usr/local/vufind
  sudo setfacl -Rm g:jacsan:rwX,u:jacsan:rwX /var/log/vufind
  sudo setfacl -dRm g:jacsan:rwX,u:jacsan:rwX /var/log/vufind
  sudo setfacl -Rm g:jacsan:rwX,u:jacsan:rwX /tmp
  sudo setfacl -dRm g:jacsan:rwX,u:jacsan:rwX /tmp
